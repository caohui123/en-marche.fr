<?php

namespace Tests\AppBundle\Controller\EnMarche;

use AppBundle\DataFixtures\ORM\LoadAdherentData;
use AppBundle\DataFixtures\ORM\LoadMissionTypeData;
use AppBundle\DataFixtures\ORM\LoadSummaryData;
use AppBundle\Entity\MemberSummary\Language;
use AppBundle\Form\SummaryType;
use AppBundle\Membership\ActivityPositions;
use AppBundle\Summary\Contract;
use AppBundle\Summary\Contribution;
use AppBundle\Summary\JobDuration;
use AppBundle\Summary\JobLocation;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\AppBundle\Controller\ControllerTestTrait;
use Tests\AppBundle\SqliteWebTestCase;

/**
 * @group functional
 */
class SummaryManagerControllerTest extends SqliteWebTestCase
{
    use ControllerTestTrait;

    private const SECTION_HEADER = 'summary.header';
    private const SECTION_SYNTHESIS = 'summary.synthesis';
    private const SECTION_MISSIONS = 'summary.missions';
    private const SECTION_MOTIVATION = 'summary.motivation';
    private const SECTION_EXPERIENCES = 'summary.experiences';
    private const SECTION_RECENT_ACTIVITIES = 'summary.recent_activities';
    private const SECTION_SKILLS = 'summary.skills';
    private const SECTION_LANGUAGES = 'summary.languages';
    private const SECTION_TRAININGS = 'summary.trainings';
    private const SECTION_INTERESTS = 'summary.interests';
    private const SECTION_CONTACT = 'summary.contact';

    private const SECTIONS = [
        self::SECTION_HEADER,
        self::SECTION_SYNTHESIS,
        self::SECTION_MISSIONS,
        self::SECTION_MOTIVATION,
        self::SECTION_RECENT_ACTIVITIES,
        self::SECTION_EXPERIENCES,
        self::SECTION_SKILLS,
        self::SECTION_LANGUAGES,
        self::SECTION_TRAININGS,
        self::SECTION_INTERESTS,
        self::SECTION_CONTACT,
    ];

    public function provideActions()
    {
        yield 'Index' => ['/espace-adherent/mon-cv'];
        yield 'Handle experience' => ['/espace-adherent/mon-cv/experience'];
        yield 'Handle training' => ['/espace-adherent/mon-cv/formation'];
        yield 'Handle language' => ['/espace-adherent/mon-cv/langue'];
        yield 'Handle skills' => ['/espace-adherent/mon-cv/competences'];

        foreach (SummaryType::STEPS as $step) {
            yield 'Handle step '.$step => ['/espace-adherent/mon-cv/'.$step];
        }
    }

    /**
     * @dataProvider provideActions
     */
    public function testActionsAreForbiddenAsAnonymous(string $path)
    {
        $this->client->request(Request::METHOD_GET, $path);
        $this->assertClientIsRedirectedTo('http://localhost/espace-adherent/connexion', $this->client);
    }

    /**
     * @dataProvider provideActions
     */
    public function testActionsAreSuccessfulAsAdherentWithSummary(string $path)
    {
        $this->authenticateAsAdherent($this->client, 'carl999@example.fr', 'secret!12345');

        $this->client->request(Request::METHOD_GET, $path);

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
    }

    /**
     * @dataProvider provideActions
     */
    public function testActionsAreSuccessfulAsAdherentWithoutSummary(string $path)
    {
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $this->client->request(Request::METHOD_GET, $path);

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testCreateExperience()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(0, $crawler->filter('.summary-experience'));

        $crawler = $this->client->click($crawler->filter('#summary-experiences .summary-add-item')->link());

        $company = 'Example';
        $position = 'Tester';

        $this->client->submit($crawler->filter('form[name=job_experience]')->form([
            'job_experience[company]' => $company,
            'job_experience[website]' => 'example.org',
            'job_experience[position]' => $position,
            'job_experience[location]' => 'Somewhere over the rainbow',
            'job_experience[started_at][month]' => '2',
            'job_experience[started_at][year]' => '2012',
            'job_experience[ended_at][month]' => '2',
            'job_experience[ended_at][year]' => '2012',
            'job_experience[contract]' => Contract::TEMPORARY,
            'job_experience[duration]' => JobDuration::FULL_TIME,
            'job_experience[description]' => 'Lorem ipsum',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('L\'expérience a bien été sauvegardée.', $crawler->filter('.flash__inner')->text());
        $this->assertCount(1, $experience = $crawler->filter('.summary-experience'));
        $this->assertContains($position, $experience->filter('h3')->text());
        $this->assertSame($company, $experience->filter('h4')->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testCreateExperienceChangesOrder()
    {
        // This adherent has a summary and experiences already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $crawler->filter('.summary-experience'));

        $crawler = $this->client->click($crawler->filter('#summary-experiences .summary-add-item')->link());

        $company = 'Example';

        $this->client->submit($crawler->filter('form[name=job_experience]')->form([
            'job_experience[company]' => $company,
            'job_experience[website]' => 'example.org',
            'job_experience[position]' => 'Tester',
            'job_experience[location]' => 'Somewhere over the rainbow',
            'job_experience[started_at][month]' => '2',
            'job_experience[started_at][year]' => '2012',
            'job_experience[ended_at][month]' => '2',
            'job_experience[ended_at][year]' => '2012',
            'job_experience[contract]' => Contract::TEMPORARY,
            'job_experience[duration]' => JobDuration::FULL_TIME,
            'job_experience[description]' => 'Lorem ipsum',
            'job_experience[display_order][entry]' => '1',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(3, $experiences = $crawler->filter('.summary-experience'));
        $this->assertSame($company, $experiences->eq(0)->filter('h4')->text());

        $summary = $this->getSummaryRepository()->findOneForAdherent($this->getAdherent(LoadAdherentData::ADHERENT_4_UUID));

        $this->assertCount(3, $experiences = $summary->getExperiences());

        foreach ($experiences as $experience) {
            switch ($experience->getDisplayOrder()) {
                case 1:
                    $this->assertSame('Example', $experience->getCompany());
                    break;
                case 2:
                    $this->assertSame('Institut KNURE', $experience->getCompany());
                    break;
                case 3:
                    $this->assertSame('Univérsité Lyon 1', $experience->getCompany());
                    break;
            }
        }
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testEditExperienceChangesOrder()
    {
        // This adherent has a summary and experiences already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $experiences = $crawler->filter('.summary-experience'));

        $lastExperience = $experiences->eq(1);

        $this->assertSame('Univérsité Lyon 1', $lastExperience->filter('h4')->text());

        $crawler = $this->client->click($lastExperience->selectLink('Modifier')->link());

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $newPosition = 1;

        $this->client->submit($crawler->filter('form[name=job_experience]')->form([
            'job_experience[display_order][entry]' => $newPosition,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertCount(2, $crawler->filter('.summary-experience'));
        $this->assertSame('Univérsité Lyon 1', $crawler->filter('.summary-experience h4')->eq(0)->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testDeleteExperienceChangesOrder()
    {
        // This adherent has a summary and experiences already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $experiences = $crawler->filter('.summary-experience'));

        $lastExperience = $experiences->eq(1);

        $this->assertSame('Univérsité Lyon 1', $lastExperience->filter('h4')->text());

        $this->client->submit($crawler->filter('.summary-experience')->eq(0)->selectButton('Supprimer')->form());

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(1, $crawler->filter('.summary-experience'));

        $summary = $this->getSummaryRepository()->findOneForAdherent($this->getAdherent(LoadAdherentData::ADHERENT_4_UUID));

        $this->assertCount(1, $experiences = $summary->getExperiences());

        $firstExperience = $experiences->first();

        $this->assertSame('Univérsité Lyon 1', $firstExperience->getCompany());
        $this->assertSame(1, $firstExperience->getDisplayOrder());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testCreateTraining()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(0, $crawler->filter('.summary-training'));

        $crawler = $this->client->click($crawler->filter('#summary-trainings .summary-add-item')->link());

        $organization = 'Example';
        $diploma = 'Master';
        $studyField = 'Web development';

        $this->client->submit($crawler->filter('form[name=training]')->form([
            'training[organization]' => $organization,
            'training[diploma]' => $diploma,
            'training[study_field]' => $studyField,
            'training[started_at][month]' => '2',
            'training[started_at][year]' => '2012',
            'training[ended_at][month]' => '2',
            'training[ended_at][year]' => '2012',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('La formation a bien été sauvegardée.', $crawler->filter('.flash__inner')->text());
        $this->assertCount(1, $experience = $crawler->filter('.summary-training'));
        $this->assertSame($diploma.' - '.$studyField, $experience->filter('h3')->text());
        $this->assertSame(strtoupper($organization), $experience->filter('h4')->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testCreateTrainingChangesOrder()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $crawler->filter('.summary-training'));

        $crawler = $this->client->click($crawler->filter('#summary-trainings .summary-add-item')->link());

        $diploma = 'Master';

        $this->client->submit($crawler->filter('form[name=training]')->form([
            'training[organization]' => 'Example',
            'training[diploma]' => $diploma,
            'training[study_field]' => 'Web development',
            'training[started_at][month]' => '2',
            'training[started_at][year]' => '2012',
            'training[ended_at][month]' => '2',
            'training[ended_at][year]' => '2012',
            'training[display_order][entry]' => '1',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(3, $crawler->filter('.summary-training'));
        $this->assertContains($diploma, $crawler->filter('.summary-training h3')->eq(0)->text());

        $summary = $this->getSummaryRepository()->findOneForAdherent($this->getAdherent(LoadAdherentData::ADHERENT_4_UUID));

        $this->assertCount(3, $trainings = $summary->getTrainings());

        foreach ($trainings as $training) {
            switch ($training->getDisplayOrder()) {
                case 1:
                    $this->assertSame($diploma, $training->getDiploma());
                    break;
                case 2:
                    $this->assertSame('Diplôme d\'ingénieur', $training->getDiploma());
                    break;
                case 3:
                    $this->assertSame('DUT Génie biologique', $training->getDiploma());
                    break;
            }
        }
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testEditTraingChangesOrder()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $trainings = $crawler->filter('.summary-training'));

        $lastTraining = $trainings->eq(1);

        $this->assertSame('DUT Génie biologique - Bio-Informatique', $lastTraining->filter('h3')->text());

        $crawler = $this->client->click($lastTraining->selectLink('Modifier')->link());

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $newPosition = 1;

        $this->client->submit($crawler->filter('form[name=training]')->form([
            'training[display_order][entry]' => $newPosition,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertCount(2, $crawler->filter('.summary-training'));
        $this->assertSame('DUT Génie biologique - Bio-Informatique', $crawler->filter('.summary-training h3')->eq(0)->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testDeleteTrainingChangesOrder()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $trainings = $crawler->filter('.summary-training'));

        $lastTraining = $trainings->eq(1);

        $this->assertSame('DUT Génie biologique - Bio-Informatique', $lastTraining->filter('h3')->text());

        $this->client->submit($crawler->filter('.summary-training')->eq(0)->selectButton('Supprimer')->form());

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(1, $crawler->filter('.summary-training'));

        $summary = $this->getSummaryRepository()->findOneForAdherent($this->getAdherent(LoadAdherentData::ADHERENT_4_UUID));

        $this->assertCount(1, $trainings = $summary->getTrainings());

        $firstTraining = $trainings->first();

        $this->assertSame('DUT Génie biologique', $firstTraining->getDiploma());
        $this->assertSame(1, $firstTraining->getDisplayOrder());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testCreateLanguageWithoutSummary()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(0, $crawler->filter('.summary-language'));

        $crawler = $this->client->click($crawler->filter('#summary-languages .summary-add-item')->link());

        $code = 'fr';
        $level = Language::LEVEL_FLUENT;

        $this->client->submit($crawler->filter('form[name=language]')->form([
            'language[code]' => $code,
            'language[level]' => $level,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('La langue a bien été sauvegardée.', $crawler->filter('.flash__inner')->text());
        $this->assertCount(1, $language = $crawler->filter('.summary-language'));
        $this->assertSame('Français - '.ucfirst($level), $language->filter('p')->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testCreateLanguageWithSummary()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(3, $crawler->filter('.summary-language'));

        $crawler = $this->client->click($crawler->filter('#summary-languages .summary-add-item')->link());

        $code = 'fr';
        $level = Language::LEVEL_FLUENT;

        $this->client->submit($crawler->filter('form[name=language]')->form([
            'language[code]' => $code,
            'language[level]' => $level,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $this->assertCount(4, $crawler->filter('.summary-language'));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testEditLanguage()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $firstLanguage = $crawler->filter('.summary-language')->eq(2);

        $this->assertSame('Espagnol - Bonne maîtrise', $firstLanguage->filter('p')->text());

        $crawler = $this->client->click($firstLanguage->selectLink('Modifier')->link());

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $newLevel = Language::LEVEL_HIGH;

        $this->client->submit($crawler->filter('form[name=language]')->form([
            'language[level]' => $newLevel,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertCount(3, $crawler->filter('.summary-language'));
        $this->assertSame('Espagnol - Maîtrise parfaite', $crawler->filter('.summary-language p')->eq(2)->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testDeleteLanguage()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(3, $languages = $crawler->filter('.summary-language'));

        $firstLanguage = $languages->eq(0);

        $this->assertSame('Français - Langue maternelle', $firstLanguage->filter('p')->text());

        $this->client->submit($firstLanguage->selectButton('Supprimer')->form());

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(2, $crawler->filter('.summary-language'));
        $this->assertSame('Anglais - Maîtrise parfaite', $crawler->filter('.summary-language p')->eq(0)->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testAddSkillWithoutSummary()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(0, $crawler->filter('.summary-skill'));

        $crawler = $this->client->click($crawler->filter('#summary-skills .summary-modify')->link());

        $skill1 = 'Développement';
        $skill2 = 'Gestion des bases';

        $form = $crawler->filter('form[name=summary]')->form();
        $form->setValues(array(
            'summary[skills][1][name]' => $skill1,
            'summary[skills][2][name]' => $skill2,
        ));
        $formData = array(
            'summary[skills][1][name]' => $skill1,
            'summary[skills][2][name]' => $skill2,
        );
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles(), array(), http_build_query($formData));
        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('Les compétences ont bien été modifiées.', $crawler->filter('.flash__inner')->text());
        $this->assertCount(2, $skills = $crawler->filter('.summary-skill'));
        $this->assertSame($skill1, $skills[0]->filter('p')->text());
        $this->assertSame($skill2, $skills[1]->filter('p')->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testModifySkillsWithSummary()
    {
        // This adherent has a summary and skills already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(4, $crawler->filter('.summary-skill'));

        $crawler = $this->client->click($crawler->filter('#summary-skills .summary-modify')->link());

        $skill1 = 'Développement';
        $skill2 = 'Gestion des bases';

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[skills][1][name]' => $skill1,
            'summary[skills][2][name]' => $skill2,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $this->assertSame('Les compétences ont bien été modifiées.', $crawler->filter('.flash__inner')->text());
        $this->assertCount(2, $skills = $crawler->filter('.summary-skill'));
        $this->assertSame($skill1, $skills[0]->filter('p')->text());
        $this->assertSame($skill2, $skills[1]->filter('p')->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepSynthesisWithoutSummary()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/synthesis');

        $this->assertCount(10, $crawler->filter('form[name=summary] input'));
        $this->assertCount(1, $crawler->filter('form[name=summary] select'));
        $this->assertCount(1, $crawler->filter('form[name=summary] textarea'));

        $profession = 'Professeur';
        $synopsis = 'This should be a professional synopsis.';

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[current_profession]' => $profession,
            'summary[current_position]' => ActivityPositions::UNEMPLOYED,
            'summary[contribution_wish]' => Contribution::VOLUNTEER,
            'summary[availabilities]' => [JobDuration::PART_TIME],
            'summary[job_locations][0]' => JobLocation::ON_SITE,
            'summary[job_locations][1]' => JobLocation::ON_REMOTE,
            'summary[professional_synopsis]' => $synopsis,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $synthesis = $this->getSummarySection($crawler, self::SECTION_SYNTHESIS);

        $this->assertSame($profession, $synthesis->filter('h2')->text());
        $this->assertSame('En recherche d\'emploi', $synthesis->filter('h3')->text());
        $this->assertCount(1, $synthesis->filter('p:contains("Missions de bénévolat")'));
        $this->assertCount(1, $synthesis->filter('p:contains("Temps partiel")'));
        $this->assertCount(1, $synthesis->filter('p:contains("Sur site ou à distance")'));
        $this->assertCount(1, $synthesis->filter(sprintf('p:contains("%s")', $synopsis)));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepSynthesisWithSummary()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/synthesis');

        $this->assertCount(10, $crawler->filter('form[name=summary] input'));
        $this->assertCount(1, $crawler->filter('form[name=summary] select'));
        $this->assertCount(1, $crawler->filter('form[name=summary] textarea'));

        $profession = 'Professeur';
        $synopsis = 'This should be a professional synopsis.';

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[current_profession]' => $profession,
            'summary[current_position]' => ActivityPositions::UNEMPLOYED,
            'summary[contribution_wish]' => Contribution::VOLUNTEER,
            'summary[availabilities]' => [JobDuration::PART_TIME],
            'summary[job_locations][1]' => JobLocation::ON_REMOTE,
            'summary[professional_synopsis]' => $synopsis,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $synthesis = $this->getSummarySection($crawler, self::SECTION_SYNTHESIS);

        $this->assertSame($profession, $synthesis->filter('h2')->text());
        $this->assertSame('En recherche d\'emploi', $synthesis->filter('h3')->text());
        $this->assertCount(1, $synthesis->filter('p:contains("Missions de bénévolat")'));
        $this->assertCount(1, $synthesis->filter('p:contains("Temps partiel")'));
        $this->assertCount(1, $synthesis->filter('p:contains("À distance")'));
        $this->assertCount(1, $synthesis->filter(sprintf('p:contains("%s")', $synopsis)));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepMissionsWithoutSummary()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/missions');

        $this->assertCount(7, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(0, $crawler->filter('form[name=summary] textarea'));

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[mission_type_wishes][0]' => '1',
            'summary[mission_type_wishes][2]' => '3',
            'summary[mission_type_wishes][4]' => '5',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_MISSIONS);

        $this->assertCount(3, $missions->filter('.summary-wish'));
        $this->assertSame('MISSIONS DE BÉNÉVOLAT', trim($missions->filter('.summary-wish')->eq(0)->text()));
        $this->assertSame('ACTION PUBLIQUE', trim($missions->filter('.summary-wish')->eq(1)->text()));
        $this->assertSame('ECONOMIE', trim($missions->filter('.summary-wish')->eq(2)->text()));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepMissionsWithSummary()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/missions');

        $this->assertCount(7, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(0, $crawler->filter('form[name=summary] textarea'));

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[mission_type_wishes][1]' => '2',
            'summary[mission_type_wishes][3]' => '4',
            'summary[mission_type_wishes][5]' => '6',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_MISSIONS);

        $this->assertCount(3, $missions->filter('.summary-wish'));
        $this->assertSame('MISSION LOCALE', trim($missions->filter('.summary-wish')->eq(0)->text()));
        $this->assertSame('ENGAGEMENT', trim($missions->filter('.summary-wish')->eq(1)->text()));
        $this->assertSame('EMPLOI', trim($missions->filter('.summary-wish')->eq(2)->text()));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepMotivationWithoutSummary()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/motivation');

        $this->assertCount(1, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(1, $crawler->filter('form[name=summary] textarea'));

        $motivation = 'I\'m motivated.';

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[motivation]' => $motivation,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_MOTIVATION);

        $this->assertCount(1, $missions->filter('p'));
        $this->assertSame($motivation, trim($missions->filter('p')->text()));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepMotivationWithSummary()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/motivation');

        $this->assertCount(1, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(1, $crawler->filter('form[name=summary] textarea'));

        $motivation = 'I\'m motivated.';

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[motivation]' => $motivation,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_MOTIVATION);

        $this->assertCount(1, $missions->filter('p'));
        $this->assertSame($motivation, trim($missions->filter('p')->text()));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepInterestsWithoutSummary()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/interests');

        $this->assertCount(19, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(0, $crawler->filter('form[name=summary] textarea'));

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[member_interests][0]' => 'agriculture',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_INTERESTS);

        $this->assertCount(1, $missions->filter('p'));
        $this->assertSame('Agriculture', trim($missions->filter('p')->text()));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepInterestsWithSummary()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/interests');

        $this->assertCount(19, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(0, $crawler->filter('form[name=summary] textarea'));

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[member_interests][4]' => 'egalite',
            'summary[member_interests][10]' => 'jeunesse',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_INTERESTS);

        $this->assertCount(2, $missions->filter('p'));
        $this->assertSame('Egalité F / H', trim($missions->filter('p')->eq(0)->text()));
        $this->assertSame('Jeunesse', trim($missions->filter('p')->eq(1)->text()));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepContactWithoutSummary()
    {
        $summariesCount = count($this->getSummaryRepository()->findAll());

        // This adherent has no summary yet
        $this->authenticateAsAdherent($this->client, 'gisele-berthoux@caramail.com', 'ILoveYouManu');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/contact');

        $this->assertCount(7, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(0, $crawler->filter('form[name=summary] textarea'));

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[contact_email]' => 'toto@example.org',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(++$summariesCount, $this->getSummaryRepository()->findAll());
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_CONTACT);

        $this->assertCount(1, $missions->filter('.summary-contact-email'));
        $this->assertCount(0, $missions->filter('.summary-contact-facebook'));
        $this->assertCount(0, $missions->filter('.summary-contact-linked_in'));
        $this->assertCount(0, $missions->filter('.summary-contact-twitter'));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testStepContactWithSummary()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv/contact');

        $this->assertCount(7, $crawler->filter('form[name=summary] input'));
        $this->assertCount(0, $crawler->filter('form[name=summary] select'));
        $this->assertCount(0, $crawler->filter('form[name=summary] textarea'));

        $this->client->submit($crawler->filter('form[name=summary]')->form([
            'summary[contact_email]' => 'toto@example.org',
            'summary[linked_in_url]' => 'https://linkedin.com/in/lucieoliverafake',
            'summary[website_url]' => 'https://lucieoliverafake.com',
            'summary[facebook_url]' => 'https://facebook.com/lucieoliverafake',
            'summary[twitter_nickname]' => 'lucieoliverafake',
            'summary[viadeo_url]' => 'http://fr.viadeo.com/fr/profile/lucie.olivera.fake',
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertSame('Vos modifications ont bien été enregistrées.', $crawler->filter('.flash__inner')->text());

        $missions = $this->getSummarySection($crawler, self::SECTION_CONTACT);

        $this->assertCount(1, $missions->filter('.summary-contact-email'));
        $this->assertCount(1, $missions->filter('.summary-contact-facebook'));
        $this->assertCount(1, $missions->filter('.summary-contact-linked_in'));
        $this->assertCount(1, $missions->filter('.summary-contact-twitter'));
        $this->assertCount(1, $missions->filter('.summary-contact-twitter'));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->init([
            LoadAdherentData::class,
            LoadSummaryData::class,
            LoadMissionTypeData::class,
        ]);
    }

    protected function tearDown()
    {
        $this->kill();

        parent::tearDown();
    }

    private function getSummarySection(Crawler $crawler, string $section): Crawler
    {
        return $crawler->filter('.adherent_summary section')->eq(array_search($section, self::SECTIONS));
    }
}
