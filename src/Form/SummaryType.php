<?php

namespace AppBundle\Form;

use AppBundle\Entity\MissionTypeEntityType;
use AppBundle\Entity\Summary;
use League\Flysystem\Filesystem;
use League\Glide\Server;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SummaryType extends AbstractType
{
    const STEP_SYNTHESIS = 'synthesis';
    const STEP_MISSION_WISHES = 'missions';
    const STEP_MOTIVATION = 'motivation';
    const STEP_SKILLS = 'competences';
    const STEP_INTERESTS = 'interests';
    const STEP_CONTACT = 'contact';

    const STEPS = [
        self::STEP_SYNTHESIS,
        self::STEP_MISSION_WISHES,
        self::STEP_MOTIVATION,
        self::STEP_SKILLS,
        self::STEP_INTERESTS,
        self::STEP_CONTACT,
    ];

    /**
     * @var Filesystem
     */
    private $storage;

    /**
     * @var Server
     */
    private $glide;

    public function __construct(Filesystem $storage, Server $glide)
    {
        $this->storage = $storage;
        $this->glide = $glide;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        switch ($options['step']) {
            case self::STEP_SYNTHESIS:
                $builder
                    ->add('current_profession', TextType::class, [
                        'required' => false,
                        'empty_data' => null,
                    ])
                    ->add('current_position', ActivityPositionType::class)
                    ->add('contribution_wish', ContributionChoiceType::class)
                    ->add('availabilities', JobDurationChoiceType::class)
                    ->add('job_locations', JobLocationChoiceType::class)
                    ->add('professional_synopsis', TextareaType::class)
                    ->add('profilePicture', FileType::class, [
                        'required' => false,
                        'label' => false,
                    ]);

                $builder
                    ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                        if (!$event->getForm()->isValid()) {
                            return;
                        }

                        /** @var Summary $summary */
                        $summary = $event->getData();
                        // Save profile picture to cloud storage
                        if ($summary->getProfilePicture()) {
                            $pathImage = 'images/'.$summary->getMemberUuid().'.jpg';
                            $this->storage->put($pathImage, file_get_contents($summary->getProfilePicture()->getPathname()));
                            $this->glide->deleteCache($pathImage);
                            $path = $this->glide->makeImage($pathImage, [
                                'w' => 500,
                                'h' => 500,
                                'q' => 70,
                                'fm' => 'jpeg',
                            ]);
                            $this->storage->put($pathImage, $this->glide->getCache()->readStream($path));
                            $this->glide->deleteCache($pathImage);
                        }
                    }, -10);
                break;

            case self::STEP_MISSION_WISHES:
                $builder
                    ->add('mission_type_wishes', MissionTypeEntityType::class)
                ;
                break;

            case self::STEP_MOTIVATION:
                $builder
                    ->add('motivation', TextareaType::class)
                ;
                break;

            case self::STEP_SKILLS:
                $builder
                    ->add('skills', CollectionType::class, [
                        'entry_type' => SkillType::class,
                        'allow_add' => true,
                        'allow_delete' => true,
                    ])
                ;
                break;

            case self::STEP_INTERESTS:
                $builder
                    ->add('member_interests', MemberInterestsChoiceType::class)
                ;
                break;

            case self::STEP_CONTACT:
                $builder
                    ->add('contact_email', EmailType::class)
                    ->add('linked_in_url', UrlType::class, [
                        'required' => false,
                    ])
                    ->add('website_url', UrlType::class, [
                        'required' => false,
                    ])
                    ->add('facebook_url', UrlType::class, [
                        'required' => false,
                    ])
                    ->add('twitter_nickname', UrlType::class, [
                        'required' => false,
                    ])
                    ->add('viadeo_url', UrlType::class, [
                        'required' => false,
                    ])
                ;
                break;
        }

        $builder->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => Summary::class,
                'validation_groups' => function (Options $options) {
                    return $options['step'];
                },
                'error_mapping' => [
                    'validAvailabilities' => 'availabilities',
                    'validJobLocations' => 'job_locations',
                ],
            ])
            ->setRequired('step')
            ->setAllowedValues('step', self::STEPS)
        ;
    }

    public static function stepExists(string $step): bool
    {
        return in_array($step, self::STEPS, true);
    }
}
