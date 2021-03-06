<?php

namespace Tests\AppBundle\Committee\Serializer;

use AppBundle\Committee\Serializer\AdherentCsvSerializer;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\PostAddress;
use AppBundle\Membership\AdherentFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;

class AdherentCsvSerializerTest extends TestCase
{
    public function testSerialize()
    {
        $adherents = [
            $this->createAdherentFromArray([
                'email' => 'michel.dufour@example.fr',
                'first_name' => 'Michel',
                'last_name' => 'Dufour',
                'password' => 'notNeededHere',
                'gender' => 'male',
                'birthdate' => new \DateTime('now - 44 years'),
                'address' => PostAddress::createFrenchAddress('36 avenue Général Leclerc', '77000-77288'),
            ]),
            $this->createAdherentFromArray([
                'email' => 'carl999@example.fr',
                'first_name' => 'Carl',
                'last_name' => 'Mirabeau',
                'password' => 'notNeededHere',
                'gender' => 'male',
                'birthdate' => new \DateTime('now - 66 years'),
                'address' => PostAddress::createFrenchAddress('36 rue Grande', '77300-77186'),
            ]),
            $this->createAdherentFromArray([
                'email' => 'naugthy.user+en-marche@gmail.com', //@see https://support.google.com/mail/answer/22370?hl=en
                'first_name' => "Jean_Pierre-André dît 'JPA'",
                'last_name' => '(Docteur) De "Maupassant"',
                'password' => 'notNeededHere',
                'gender' => 'male',
                'birthdate' => new \DateTime('now - 22 years'),
                'address' => PostAddress::createFrenchAddress('36 rue de la Paix', '75008-75108'),
            ]),
        ];

        $csv = [
            'Prénom,Nom,Age,"Code postal",Ville',
            'Michel,D.,44,77000,Melun',
            'Carl,M.,66,77300,Fontainebleau',
            '"Jean_Pierre-André dît \'JPA\'",D.,22,75008,"Paris 8e"',
            '',
        ];

        $this->assertEquals($csv[0]."\n", AdherentCsvSerializer::serialize([]));
        $this->assertCount(5, explode("\n", AdherentCsvSerializer::serialize($adherents)));
        $this->assertEquals(implode($csv, "\n"), AdherentCsvSerializer::serialize($adherents));
    }

    public function testSerializeBadCallCollection()
    {
        $this->expectException(\BadMethodCallException::class);

        AdherentCsvSerializer::serialize(['this is not a collection of adherents']);
    }

    public function testSerializeBadCallIterable()
    {
        $this->expectException(\BadMethodCallException::class);

        AdherentCsvSerializer::serialize('this is not an iterable value');
    }

    private function createAdherentFromArray(array $data): Adherent
    {
        $factory = new AdherentFactory(new EncoderFactory([
            Adherent::class => new PlaintextPasswordEncoder(),
        ]));

        return $factory->createFromArray($data);
    }
}
