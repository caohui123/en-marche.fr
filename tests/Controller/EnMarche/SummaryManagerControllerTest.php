<?php

namespace Tests\AppBundle\Controller\EnMarche;

use AppBundle\DataFixtures\ORM\LoadAdherentData;
use AppBundle\DataFixtures\ORM\LoadSummaryData;
use AppBundle\Entity\MemberSummary\Language;
use AppBundle\Summary\Contract;
use AppBundle\Summary\JobDuration;
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

    public function provideActions()
    {
        yield 'Index' => ['/espace-adherent/mon-cv'];
        yield 'Handle experience' => ['/espace-adherent/mon-cv/experience'];
        yield 'Handle training' => ['/espace-adherent/mon-cv/formation'];
        yield 'Handle language' => ['/espace-adherent/mon-cv/langue'];
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

        $this->assertCount(0, $crawler->filter('.cv__experience'));

        $crawler = $this->client->click($crawler->filter('#summary-experiences .summary-add-item')->link());

        $company = 'Example';
        $position = 'TESTER';

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
        $this->assertCount(1, $experience = $crawler->filter('.cv__experience > div'));
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

        $this->assertCount(2, $crawler->filter('.cv__experience'));

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
        $this->assertCount(3, $experiences = $crawler->filter('.cv__experience'));
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

        $this->assertCount(2, $experiences = $crawler->filter('.cv__experience'));

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

        $this->assertCount(2, $crawler->filter('.cv__experience'));
        $this->assertSame('Univérsité Lyon 1', $crawler->filter('.cv__experience h4')->eq(0)->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testDeleteExperienceChangesOrder()
    {
        // This adherent has a summary and experiences already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $experiences = $crawler->filter('.cv__experience'));

        $lastExperience = $experiences->eq(1);

        $this->assertSame('Univérsité Lyon 1', $lastExperience->filter('h4')->text());

        $this->client->submit($crawler->filter('.cv__experience')->eq(0)->selectButton('Supprimer')->form());

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(1, $crawler->filter('.cv__experience'));

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

        $this->assertCount(0, $crawler->filter('.cv__training'));

        $crawler = $this->client->click($crawler->filter('#summary-trainings .summary-add-item')->link());

        $organization = 'EXAMPLE';
        $diploma = 'MASTER';
        $studyField = 'WEB DEVELOPMENT';

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
        $this->assertCount(1, $experience = $crawler->filter('.cv__training'));
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

        $this->assertCount(2, $crawler->filter('.cv__training'));

        $crawler = $this->client->click($crawler->filter('#summary-trainings .summary-add-item')->link());

        $diploma = 'MASTER';

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
        $this->assertCount(3, $crawler->filter('.cv__training'));
        $this->assertContains($diploma, $crawler->filter('.cv__training h3')->eq(0)->text());

        $summary = $this->getSummaryRepository()->findOneForAdherent($this->getAdherent(LoadAdherentData::ADHERENT_4_UUID));

        $this->assertCount(3, $trainings = $summary->getTrainings());

        foreach ($trainings as $training) {
            switch ($training->getDisplayOrder()) {
                case 1:
                    $this->assertSame($diploma, $training->getDiploma());
                    break;
                case 2:
                    $this->assertSame('DIPLÔME D\'INGÉNIEUR', $training->getDiploma());
                    break;
                case 3:
                    $this->assertSame('DUT GÉNIE BIOLOGIQUE', $training->getDiploma());
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

        $this->assertCount(2, $trainings = $crawler->filter('.cv__training'));

        $lastTraining = $trainings->eq(1);

        $this->assertSame('DUT GÉNIE BIOLOGIQUE - BIO-INFORMATIQUE', $lastTraining->filter('h3')->text());

        $crawler = $this->client->click($lastTraining->selectLink('Modifier')->link());

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $newPosition = 1;

        $this->client->submit($crawler->filter('form[name=training]')->form([
            'training[display_order][entry]' => $newPosition,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertCount(2, $crawler->filter('.cv__training'));
        $this->assertSame('DUT GÉNIE BIOLOGIQUE - BIO-INFORMATIQUE', $crawler->filter('.cv__training h3')->eq(0)->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testDeleteTrainingChangesOrder()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(2, $trainings = $crawler->filter('.cv__training'));

        $lastTraining = $trainings->eq(1);

        $this->assertSame('DUT GÉNIE BIOLOGIQUE - BIO-INFORMATIQUE', $lastTraining->filter('h3')->text());

        $this->client->submit($crawler->filter('.cv__training')->eq(0)->selectButton('Supprimer')->form());

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(1, $crawler->filter('.cv__training'));

        $summary = $this->getSummaryRepository()->findOneForAdherent($this->getAdherent(LoadAdherentData::ADHERENT_4_UUID));

        $this->assertCount(1, $trainings = $summary->getTrainings());

        $firstTraining = $trainings->first();

        $this->assertSame('DUT GÉNIE BIOLOGIQUE', $firstTraining->getDiploma());
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

        $this->assertCount(0, $crawler->filter('.cv__languages'));

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
        $this->assertCount(1, $language = $crawler->filter('.cv__languages'));
        $this->assertSame('Français - '.ucfirst($level), $language->filter('.cv__languages > div')->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testCreateLanguageWithSummary()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(3, $crawler->filter('.cv__languages'));

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

        $this->assertCount(4, $crawler->filter('.cv__languages'));
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testEditLanguage()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $firstLanguage = $crawler->filter('.cv__languages')->eq(2);

        $this->assertSame('Espagnol - Bonne maîtrise', $firstLanguage->filter('.cv__languages > div')->text());

        $crawler = $this->client->click($firstLanguage->selectLink('Modifier')->link());

        $this->assertStatusCode(Response::HTTP_OK, $this->client);

        $newLevel = Language::LEVEL_HIGH;

        $this->client->submit($crawler->filter('form[name=language]')->form([
            'language[level]' => $newLevel,
        ]));

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertCount(3, $crawler->filter('.cv__languages'));
        $this->assertSame('Espagnol - Maîtrise parfaite', $crawler->filter('.cv__languages > div')->eq(2)->text());
    }

    /**
     * @depends testActionsAreSuccessfulAsAdherentWithoutSummary
     */
    public function testDeleteLanguage()
    {
        // This adherent has a summary and trainings already
        $this->authenticateAsAdherent($this->client, 'luciole1989@spambox.fr', 'EnMarche2017');

        $crawler = $this->client->request(Request::METHOD_GET, '/espace-adherent/mon-cv');

        $this->assertCount(3, $languages = $crawler->filter('.cv__languages'));

        $firstLanguage = $languages->eq(0);

        $this->assertSame('Français - Langue maternelle', $firstLanguage->filter('.cv__languages > div')->text());

        $this->client->submit($firstLanguage->selectButton('Supprimer')->form());

        $this->assertStatusCode(Response::HTTP_FOUND, $this->client);
        $this->assertClientIsRedirectedTo('/espace-adherent/mon-cv', $this->client);

        $crawler = $this->client->followRedirect();

        $this->assertStatusCode(Response::HTTP_OK, $this->client);
        $this->assertCount(2, $crawler->filter('.cv__languages'));
        $this->assertSame('Anglais - Maîtrise parfaite', $crawler->filter('.cv__languages > div')->eq(0)->text());
    }

    protected function setUp()
    {
        parent::setUp();

        $this->init([
            LoadAdherentData::class,
            LoadSummaryData::class,
        ]);
    }

    protected function tearDown()
    {
        $this->kill();

        parent::tearDown();
    }
}
