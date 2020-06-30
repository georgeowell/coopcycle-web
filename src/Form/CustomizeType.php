<?php

namespace AppBundle\Form;

use AppBundle\Service\SettingsManager;
use League\Flysystem\Filesystem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use Symfony\Contracts\Cache\CacheInterface;

class CustomizeType extends AbstractType
{
    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        CacheInterface $appCache)
    {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->appCache = $appCache;
    }

    private function getContentData($filename)
    {
        if ($this->assetsFilesystem->has($filename)) {

            return [
                $content = $this->assetsFilesystem->read($filename),
                true
            ];
        }

        return [
            '',
            false
        ];
    }

    private function onContentSubmit($filename, $content, $enabled)
    {
        if (empty(trim($content))) {
            $enabled = false;
        }

        if ($enabled) {
            if ($this->assetsFilesystem->has($filename)) {
                $this->assetsFilesystem->update($filename, $content);
            } else {
                $this->assetsFilesystem->write($filename, $content);
            }
        } else {
            if ($this->assetsFilesystem->has($filename)) {
                $this->assetsFilesystem->delete($filename);
            }
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('motto', TextType::class, [
                'required' => false,
                'label' => 'form.customize.motto.label',
                'attr' => ['placeholder' => 'index.banner'],
                'help' => 'form.customize.motto.help',
            ])
            ->add('aboutUsEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'form.customize.about_us_enabled.label',
            ])
            ->add('aboutUs', TextareaType::class, [
                'required' => false,
                'label' => 'form.customize.about_us.label',
                'attr' => ['rows' => '12'],
                'help' => 'mardown_formatting.help',
            ])
            ->add('customTermsEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'form.customize.custom_terms_enabled.label',
            ])
            ->add('customTerms', TextareaType::class, [
                'required' => false,
                'label' => 'form.customize.custom_terms.label',
                'attr' => ['rows' => '12'],
                'help' => 'mardown_formatting.help',
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            [ $aboutUs, $aboutUsEnabled ] = $this->getContentData('about_us.md');

            $form->get('aboutUs')->setData($aboutUs);
            $form->get('aboutUsEnabled')->setData($aboutUsEnabled);

            [ $customTerms, $customTermsEnabled ] = $this->getContentData('custom_terms.md');

            $form->get('customTerms')->setData($customTerms);
            $form->get('customTermsEnabled')->setData($customTermsEnabled);

            $motto = $this->settingsManager->get('motto');
            if (!empty($motto)) {
                $form->get('motto')->setData($motto);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            // About us

            $aboutUsEnabled = $form->get('aboutUsEnabled')->getData();
            $aboutUs = $form->get('aboutUs')->getData();

            $this->onContentSubmit(
                'about_us.md',
                $aboutUs,
                $aboutUsEnabled
            );

            $this->appCache->delete('content.about_us');
            $this->appCache->delete('content.about_us.exists');

            // Custom terms

            $customTermsEnabled = $form->get('customTermsEnabled')->getData();
            $customTerms = $form->get('customTerms')->getData();

            $this->onContentSubmit(
                'custom_terms.md',
                $customTerms,
                $customTermsEnabled
            );

            $motto = $form->get('motto')->getData();
            if (!empty($motto)) {
                $this->settingsManager->set('motto', $motto);
                $this->settingsManager->flush();
            }
        });
    }
}
