<?php

namespace Pumukit\SchemaBundle\Tests\Repository;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\SchemaBundle\Document\Pic;
use Pumukit\SchemaBundle\Document\Material;
use Pumukit\SchemaBundle\Document\Link;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\PersonInMultimediaObject;
use Pumukit\SchemaBundle\Document\SeriesType;
use Pumukit\SchemaBundle\Document\Broadcast;
use Doctrine\ODM\MongoDB\Cursor;
use Doctrine\Common\Collections\ArrayCollection;

class MultimediaObjectRepositoryTest extends WebTestCase
{
    private $dm;
    private $repo;
    private $qb;

    public function setUp()
    {
        $options = array('environment' => 'test');
        $kernel = static::createKernel($options);
        $kernel->boot();
        $this->dm = $kernel->getContainer()
            ->get('doctrine_mongodb')->getManager();
        $this->repo = $this->dm
            ->getRepository('PumukitSchemaBundle:MultimediaObject');

        //DELETE DATABASE
        // pimo has to be deleted before mmobj
        $this->dm->getDocumentCollection('PumukitSchemaBundle:PersonInMultimediaObject')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Role')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Person')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:SeriesType')
            ->remove(array());
        $this->dm->flush();
    }

    public function testRepositoryEmpty()
    {
        $this->assertEquals(0, count($this->repo->findAll()));
    }

    public function testRepository()
    {
        //$rank = 1;
        $status = MultimediaObject::STATUS_NORMAL;
        $record_date = new \DateTime();
        $public_date = new \DateTime();
        $title = 'titulo cualquiera';
        $subtitle = 'Subtitle paragraph';
        $description = "Description text";
        $duration = 300;
	$broadcast = $this->createBroadcast(Broadcast::BROADCAST_TYPE_PRI);

        $mmobj = new MultimediaObject();
        //$mmobj->setRank($rank);
        $mmobj->setStatus($status);
        $mmobj->setRecordDate($record_date);
        $mmobj->setPublicDate($public_date);
        $mmobj->setTitle($title);
        $mmobj->setSubtitle($subtitle);
        $mmobj->setDescription($description);
        $mmobj->setDuration($duration);
	$mmobj->setBroadcast($broadcast);

        $this->dm->persist($mmobj);
        $this->dm->flush();

        $this->assertEquals(1, count($this->repo->findAll()));
    }

    public function testCreateMultimediaObjectAndFindByCriteria()
    {
        $series_type = $this->createSeriesType("Medieval Fantasy Sitcom");

        $series_main = $this->createSeries("Stark's growing pains");
        $series_wall = $this->createSeries("The Wall");
        $series_lhazar = $this->createSeries("A quiet life");

        $person_ned = $this->createPerson('Ned');
        $person_benjen = $this->createPerson('Benjen');

        $role_lord = $this->createRole("Lord");
        $role_ranger = $this->createRole("First Ranger");
        $role_hand = $this->createRole("Hand of the King");

        $series_type->addSeries($series_main);

        $mm1 = $this->createMultimediaObjectAssignedToSeries('MmObject 1', $series_main);
        $mm2 = $this->createMultimediaObjectAssignedToSeries('MmObject 2', $series_wall);
        $mm3 = $this->createMultimediaObjectAssignedToSeries('MmObject 3', $series_main);
        $mm4 = $this->createMultimediaObjectAssignedToSeries('MmObject 4', $series_lhazar);

        $this->dm->persist($mm1);
        $this->dm->persist($mm2);
        $this->dm->persist($mm3);
        $this->dm->persist($mm4);
        $this->dm->flush(); // It is needed to flush multimedia objects before pimo's

        $this->addPersonWithRoleInMultimediaObject($person_ned, $role_lord, $mm1);
        $this->addPersonWithRoleInMultimediaObject($person_benjen, $role_ranger, $mm2);
        $this->addPersonWithRoleInMultimediaObject($person_ned, $role_lord, $mm3);
        $this->addPersonWithRoleInMultimediaObject($person_benjen, $role_ranger, $mm3);
        $this->addPersonWithRoleInMultimediaObject($person_ned, $role_hand, $mm4);
        $this->dm->flush();
        // DB setup END.

        // Test find by person
        $mmobj_ned = $this->getMultimediaObjectsWithPerson($person_ned);
        $this->assertEquals(3, count($mmobj_ned));

        // Test find by person and role
        $mmobj_benjen_ranger = $this->getMultimediaObjectsWithPersonAndRole($person_benjen, $role_ranger);
        $mmobj_ned_lord = $this->getMultimediaObjectsWithPersonAndRole($person_ned, $role_lord);
        $mmobj_ned_hand = $this->getMultimediaObjectsWithPersonAndRole($person_ned, $role_hand);
        $mmobj_benjen_lord = $this->getMultimediaObjectsWithPersonAndRole($person_benjen, $role_lord);
        $mmobj_ned_ranger = $this->getMultimediaObjectsWithPersonAndRole($person_ned, $role_ranger);

        $this->assertEquals(2, count($mmobj_benjen_ranger));
        $this->assertEquals(2, count($mmobj_ned_lord));
        $this->assertEquals(1, count($mmobj_ned_hand));
        $this->assertEquals(0, count($mmobj_benjen_lord));
        $this->assertEquals(0, count($mmobj_ned_ranger));

        // Test find by series
        $mmobj_series_main = $this->getMultimediaObjectsWithSeries($series_main);
        $mmobj_series_wall = $this->getMultimediaObjectsWithSeries($series_wall);
        $mmobj_series_lhazar = $this->getMultimediaObjectsWithSeries($series_lhazar);

        $this->assertEquals(2, count($mmobj_series_main));
        $this->assertEquals(1, count($mmobj_series_wall));
        $this->assertEquals(1, count($mmobj_series_lhazar));
    }

    public function testFindBySeries()
    {
        $this->assertEquals(0, count($this->repo->findAll()));
      //$this->assertEquals(4,count($this->repo->findBySeries($series_main)));
    }

    public function testFindWithStatus()
    {
        $series = $this->createSeries('Serie prueba status');

        $mmPrototype = $this->createMultimediaObjectAssignedToSeries('Status prototype', $series);
        $mmPrototype->setStatus(MultimediaObject::STATUS_PROTOTYPE);

        $mmNew = $this->createMultimediaObjectAssignedToSeries('Status new', $series);
        $mmNew->setStatus(MultimediaObject::STATUS_NEW);

        $mmHide = $this->createMultimediaObjectAssignedToSeries('Status hide', $series);
        $mmHide->setStatus(MultimediaObject::STATUS_HIDE);

        $mmBloq = $this->createMultimediaObjectAssignedToSeries('Status bloq', $series);
        $mmBloq->setStatus(MultimediaObject::STATUS_BLOQ);

        $mmNormal = $this->createMultimediaObjectAssignedToSeries('Status normal', $series);
        $mmNormal->setStatus(MultimediaObject::STATUS_NORMAL);

        $this->dm->persist($mmPrototype);
        $this->dm->persist($mmNew);
        $this->dm->persist($mmHide);
        $this->dm->persist($mmBloq);
        $this->dm->persist($mmNormal);
        $this->dm->flush();

        $this->assertEquals(1, count($this->repo->findWithStatus($series, array(MultimediaObject::STATUS_PROTOTYPE))));
        $this->assertEquals(1, count($this->repo->findWithStatus($series, array(MultimediaObject::STATUS_NEW))));
        $this->assertEquals(1, count($this->repo->findWithStatus($series, array(MultimediaObject::STATUS_HIDE))));
        $this->assertEquals(1, count($this->repo->findWithStatus($series, array(MultimediaObject::STATUS_BLOQ))));
        $this->assertEquals(1, count($this->repo->findWithStatus($series, array(MultimediaObject::STATUS_NORMAL))));
        $this->assertEquals(2, count($this->repo->findWithStatus($series, array(MultimediaObject::STATUS_PROTOTYPE, MultimediaObject::STATUS_NEW))));
        $this->assertEquals(3, count($this->repo->findWithStatus($series, array(MultimediaObject::STATUS_NORMAL, MultimediaObject::STATUS_NEW, MultimediaObject::STATUS_HIDE))));

	$mmArray = array($mmPrototype->getId() => $mmPrototype);
	$this->assertEquals($mmArray, $this->repo->findWithStatus($series, array(MultimediaObject::STATUS_PROTOTYPE))->toArray());
	$mmArray = array($mmNew->getId() => $mmNew);
        $this->assertEquals($mmArray, $this->repo->findWithStatus($series, array(MultimediaObject::STATUS_NEW))->toArray());
	$mmArray = array($mmHide->getId() => $mmHide);
        $this->assertEquals($mmArray, $this->repo->findWithStatus($series, array(MultimediaObject::STATUS_HIDE))->toArray());
	$mmArray = array($mmBloq->getId() => $mmBloq);
        $this->assertEquals($mmArray, $this->repo->findWithStatus($series, array(MultimediaObject::STATUS_BLOQ))->toArray());
	$mmArray = array($mmNormal->getId() => $mmNormal);
        $this->assertEquals($mmArray, $this->repo->findWithStatus($series, array(MultimediaObject::STATUS_NORMAL))->toArray());
	$mmArray = array($mmPrototype->getId() => $mmPrototype, $mmNew->getId() => $mmNew);
	$this->assertEquals($mmArray, $this->repo->findWithStatus($series, array(MultimediaObject::STATUS_PROTOTYPE, MultimediaObject::STATUS_NEW))->toArray());
	$mmArray = array($mmNormal->getId() => $mmNormal, $mmNew->getId() => $mmNew, $mmHide->getId() => $mmHide);
        $this->assertEquals($mmArray, $this->repo->findWithStatus($series, array(MultimediaObject::STATUS_NORMAL, MultimediaObject::STATUS_NEW, MultimediaObject::STATUS_HIDE))->toArray());
    }

    public function testFindPrototype()
    {
        $series = $this->createSeries('Serie prueba status');

        $mmPrototype = $this->createMultimediaObjectAssignedToSeries('Status prototype', $series);
        $mmPrototype->setStatus(MultimediaObject::STATUS_PROTOTYPE);

        $mmNew = $this->createMultimediaObjectAssignedToSeries('Status new', $series);
        $mmNew->setStatus(MultimediaObject::STATUS_NEW);

        $mmHide = $this->createMultimediaObjectAssignedToSeries('Status hide', $series);
        $mmHide->setStatus(MultimediaObject::STATUS_HIDE);

        $mmBloq = $this->createMultimediaObjectAssignedToSeries('Status bloq', $series);
        $mmBloq->setStatus(MultimediaObject::STATUS_BLOQ);

        $mmNormal = $this->createMultimediaObjectAssignedToSeries('Status normal', $series);
        $mmNormal->setStatus(MultimediaObject::STATUS_NORMAL);

        $this->dm->persist($mmPrototype);
        $this->dm->persist($mmNew);
        $this->dm->persist($mmHide);
        $this->dm->persist($mmBloq);
        $this->dm->persist($mmNormal);
        $this->dm->flush();

	$this->assertEquals(1, count($this->repo->findPrototype($series)));
	$this->assertEquals($mmPrototype, $this->repo->findPrototype($series));
    }

    public function testFindWithoutPrototype()
    {
        $series = $this->createSeries('Serie prueba status');

        $mmPrototype = $this->createMultimediaObjectAssignedToSeries('Status prototype', $series);
        $mmPrototype->setStatus(MultimediaObject::STATUS_PROTOTYPE);

        $mmNew = $this->createMultimediaObjectAssignedToSeries('Status new', $series);
        $mmNew->setStatus(MultimediaObject::STATUS_NEW);

        $mmHide = $this->createMultimediaObjectAssignedToSeries('Status hide', $series);
        $mmHide->setStatus(MultimediaObject::STATUS_HIDE);

        $mmBloq = $this->createMultimediaObjectAssignedToSeries('Status bloq', $series);
        $mmBloq->setStatus(MultimediaObject::STATUS_BLOQ);

        $mmNormal = $this->createMultimediaObjectAssignedToSeries('Status normal', $series);
        $mmNormal->setStatus(MultimediaObject::STATUS_NORMAL);

        $this->dm->persist($mmPrototype);
        $this->dm->persist($mmNew);
        $this->dm->persist($mmHide);
        $this->dm->persist($mmBloq);
        $this->dm->persist($mmNormal);
        $this->dm->flush();

	$this->assertEquals(4, count($this->repo->findWithoutPrototype($series)));

	$mmArray = array(
			 $mmNew->getId() => $mmNew,
			 $mmHide->getId() => $mmHide,
			 $mmBloq->getId() => $mmBloq,
			 $mmNormal->getId() => $mmNormal
			 );
	$this->assertEquals($mmArray, $this->repo->findWithoutPrototype($series)->toArray());
    }

    public function testEmbedPicsInMultimediaObject()
    {
        $pic1 = new Pic();
        $pic2 = new Pic();
        $pic3 = new Pic();

	$this->dm->persist($pic1);
	$this->dm->persist($pic2);
	$this->dm->persist($pic3);

        $mm = new MultimediaObject();
	$mm->addPic($pic1);
	$mm->addPic($pic2);
	$mm->addPic($pic3);
	
	$this->dm->persist($mm);

	$this->dm->flush();

	$this->assertEquals($pic1, $this->repo->find($mm->getId())->getPicById($pic1->getId()));
	$this->assertEquals($pic2, $this->repo->find($mm->getId())->getPicById($pic2->getId()));
	$this->assertEquals($pic3, $this->repo->find($mm->getId())->getPicById($pic3->getId()));
	$this->assertNull($this->repo->find($mm->getId())->getPicById(null));

	$mm->removePicById($pic2->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$picsArray = array($pic1, $pic3);
	$this->assertEquals(count($picsArray), count($this->repo->find($mm->getId())->getPics()));
	$this->assertEquals($picsArray, array_values($this->repo->find($mm->getId())->getPics()->toArray()));
  
	$mm->upPicById($pic3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$picsArray = array($pic3, $pic1);
	$this->assertEquals($picsArray, array_values($this->repo->find($mm->getId())->getPics()->toArray()));

	$mm->downPicById($pic3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$picsArray = array($pic1, $pic3);
	$this->assertEquals($picsArray, array_values($this->repo->find($mm->getId())->getPics()->toArray()));
    }

    public function testEmbedMaterialsInMultimediaObject()
    {
        $material1 = new Material();
        $material2 = new Material();
        $material3 = new Material();

	$this->dm->persist($material1);
	$this->dm->persist($material2);
	$this->dm->persist($material3);

        $mm = new MultimediaObject();
	$mm->addMaterial($material1);
	$mm->addMaterial($material2);
	$mm->addMaterial($material3);
	
	$this->dm->persist($mm);

	$this->dm->flush();

	$this->assertEquals($material1, $this->repo->find($mm->getId())->getMaterialById($material1->getId()));
	$this->assertEquals($material2, $this->repo->find($mm->getId())->getMaterialById($material2->getId()));
	$this->assertEquals($material3, $this->repo->find($mm->getId())->getMaterialById($material3->getId()));
	$this->assertNull($this->repo->find($mm->getId())->getMaterialById(null));

	$mm->removeMaterialById($material2->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$materialsArray = array($material1, $material3);
	$this->assertEquals(count($materialsArray), count($this->repo->find($mm->getId())->getMaterials()));
	$this->assertEquals($materialsArray, array_values($this->repo->find($mm->getId())->getMaterials()->toArray()));
  
	$mm->upMaterialById($material3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$materialsArray = array($material3, $material1);
	$this->assertEquals($materialsArray, array_values($this->repo->find($mm->getId())->getMaterials()->toArray()));

	$mm->downMaterialById($material3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$materialsArray = array($material1, $material3);
	$this->assertEquals($materialsArray, array_values($this->repo->find($mm->getId())->getMaterials()->toArray()));
    }

    public function testEmbedLinksInMultimediaObject()
    {
        $link1 = new Link();
        $link2 = new Link();
        $link3 = new Link();

	$this->dm->persist($link1);
	$this->dm->persist($link2);
	$this->dm->persist($link3);

        $mm = new MultimediaObject();
	$mm->addLink($link1);
	$mm->addLink($link2);
	$mm->addLink($link3);
	
	$this->dm->persist($mm);

	$this->dm->flush();

	$this->assertEquals($link1, $this->repo->find($mm->getId())->getLinkById($link1->getId()));
	$this->assertEquals($link2, $this->repo->find($mm->getId())->getLinkById($link2->getId()));
	$this->assertEquals($link3, $this->repo->find($mm->getId())->getLinkById($link3->getId()));
	$this->assertNull($this->repo->find($mm->getId())->getLinkById(null));

	$mm->removeLinkById($link2->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$linksArray = array($link1, $link3);
	$this->assertEquals(count($linksArray), count($this->repo->find($mm->getId())->getLinks()));
	$this->assertEquals($linksArray, array_values($this->repo->find($mm->getId())->getLinks()->toArray()));
  
	$mm->upLinkById($link3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$linksArray = array($link3, $link1);
	$this->assertEquals($linksArray, array_values($this->repo->find($mm->getId())->getLinks()->toArray()));

	$mm->downLinkById($link3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$linksArray = array($link1, $link3);
	$this->assertEquals($linksArray, array_values($this->repo->find($mm->getId())->getLinks()->toArray()));
    }

    public function testEmbedTracksInMultimediaObject()
    {
        $track1 = new Track();
        $track2 = new Track();
        $track3 = new Track();

	$this->dm->persist($track1);
	$this->dm->persist($track2);
	$this->dm->persist($track3);

        $mm = new MultimediaObject();
	$mm->addTrack($track1);
	$mm->addTrack($track2);
	$mm->addTrack($track3);
	
	$this->dm->persist($mm);

	$this->dm->flush();

	$this->assertEquals($track1, $this->repo->find($mm->getId())->getTrackById($track1->getId()));
	$this->assertEquals($track2, $this->repo->find($mm->getId())->getTrackById($track2->getId()));
	$this->assertEquals($track3, $this->repo->find($mm->getId())->getTrackById($track3->getId()));
	$this->assertNull($this->repo->find($mm->getId())->getTrackById(null));

	$mm->removeTrackById($track2->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$tracksArray = array($track1, $track3);
	$this->assertEquals(count($tracksArray), count($this->repo->find($mm->getId())->getTracks()));
	$this->assertEquals($tracksArray, array_values($this->repo->find($mm->getId())->getTracks()->toArray()));
  
	$mm->upTrackById($track3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$tracksArray = array($track3, $track1);
	$this->assertEquals($tracksArray, array_values($this->repo->find($mm->getId())->getTracks()->toArray()));

	$mm->downTrackById($track3->getId());
	$this->dm->persist($mm);
	$this->dm->flush();

	$tracksArray = array($track1, $track3);
	$this->assertEquals($tracksArray, array_values($this->repo->find($mm->getId())->getTracks()->toArray()));
    }

    private function createPerson($name)
    {
        $email = $name.'@mail.es';
        $web = 'http://www.url.com';
        $phone = '+34986123456';
        $honorific = 'honorific';
        $firm = 'firm';
        $post = 'post';
        $bio = 'Biografía extensa de la persona';

        $person = new Person();
        $person->setName($name);
        $person->setEmail($email);
        $person->setWeb($web);
        $person->setPhone($phone);
        $person->setHonorific($honorific);
        $person->setFirm($firm);
        $person->setPost($post);
        $person->setBio($bio);

        // FIXME esto no persiste.
        // Este dm (DocumentManager) se refiere
        // a doctrine_mongodb en la
        // base de datos pumukit_test
        $this->dm->persist($person);
        $this->dm->flush();

        return $person;
    }

    private function createRole($name)
    {
        $cod = $name; // string (20)
        $rank = strlen($name); // Quick and dirty way to keep it unique
        $xml = '<xml content and definition of this/>';
        $display = true;
        $text = 'Black then white are all i see in my infancy,
			red and yellow then came to be, reaching out to me,
			lets me see.
			As below, so above and beyond, I imagine
			drawn beyond the lines of reason.
			Push the envelope. Watch it bend.';
        $pimo = new PersonInMultimediaObject();

        $rol = new Role();
        $rol->setCod($cod);
        $rol->setRank($rank);
        $rol->setXml($xml);
        $rol->setDisplay($display); // true by default
        $rol->setName($name);
        $rol->setText($text);
        $rol->addPeopleInMultimediaObject($pimo);

        $this->dm->persist($rol);
        $this->dm->flush();

        return $rol;
    }

    private function createMultimediaObjectAssignedToSeries($title, Series $series)
    {
        $rank = 1;
        $status = MultimediaObject::STATUS_NORMAL;
        $record_date = new \DateTime();
        $public_date = new \DateTime();
        $subtitle = 'Subtitle';
        $description = "Description";
        $duration = 123;

        //$tag1 = new Tag('tag1');
        //$tag2 = new Tag('tag2');
        //$mm_tags = array($tag1, $tag2);

        //$track1 = new Track();

        //$pic1 = new Pic();

        //$material1 = new Material();

        $mm = new MultimediaObject();

        //$mm->addTag($tag1);
        //$mm->addTrack($track1);
        //$mm->addPic($pic1);
        //$mm->addMaterial($material1);

        $mm->setStatus($status);
        $mm->setRecordDate($record_date);
        $mm->setPublicDate($public_date);
        $mm->setTitle($title);
        $mm->setSubtitle($subtitle);
        $mm->setDescription($description);
        $mm->setDuration($duration);

        $series->addMultimediaObject($mm);

        //$this->dm->persist($tag1);
        //$this->dm->persist($track1);
        //$this->dm->persist($pic1);
        //$this->dm->persist($material1);
        $this->dm->persist($mm);
        $this->dm->flush();

        return $mm;
    }

    private function createSeries($title)
    {
        $subtitle = 'subtitle';
        $description = 'description';
        $test_date = new \DateTime("now");

        $serie = new Series();

        $serie->setTitle($title);
        $serie->setSubtitle($subtitle);
        $serie->setDescription($description);
        $serie->setPublicDate($test_date);

        $this->dm->persist($serie);
        $this->dm->flush();

        return $serie;
    }

    private function createSeriesType($name)
    {
        $description = 'description';
        $series_type = new SeriesType();

        $series_type->setName($name);
        $series_type->setDescription($description);

        $this->dm->persist($series_type);
        $this->dm->flush();

        return $series_type;
    }

    // This function was used to assure that pimo objects would persist.
    public function addPersonWithRoleInMultimediaObject(
            Person $person, Role $role, MultimediaObject $mm)
    {
        if (!$mm->containsPersonWithRole($person, $role)) {
            $pimo = new PersonInMultimediaObject();
            $pimo->setPerson($person);
            $pimo->setRole($role);
            $pimo->setMultimediaObject($mm);
            $pimo->setRank(count($mm->getPeopleInMultimediaObject()));
            $mm->addPersonInMultimediaObject($pimo);
            $this->dm->persist($pimo);
            $this->dm->flush();

            return $pimo;
        }
    }

    private function getMultimediaObjectsWithPerson(Person $person)
    {
        $pimo_cursor = $this->queryPersonInPIMO($person);
        $mmobj_array = $this->getMultimediaObjectsWithPIMO($pimo_cursor);

        return $mmobj_array;
    }

    private function queryPersonInPIMO(Person $person)
    {
        $pimo_cursor = $this->dm
                ->createQueryBuilder('PumukitSchemaBundle:PersonInMultimediaObject')
                ->field('person')->equals($person)
                ->getQuery()->execute();

        return $pimo_cursor;
    }

    private function getMultimediaObjectsWithPersonAndRole(Person $person, Role $role)
    {
        $role_object = $this->dm->find('PumukitSchemaBundle:Role', $role->getId());
        $pimo_cursor = $this->queryPersonAndRoleInPIMO($person, $role_object);
        $mmobj_array = $this->getMultimediaObjectsWithPIMO($pimo_cursor);

        return $mmobj_array;
    }

    private function queryPersonAndRoleInPIMO(Person $person, Role $role_object)
    {
        $pimo_cursor = $this->dm
                ->createQueryBuilder('PumukitSchemaBundle:PersonInMultimediaObject')
                ->field('person')->equals($person)
                ->field('role')->references($role_object)
                ->getQuery()->execute();

        return $pimo_cursor;
    }

    private function getMultimediaObjectsWithPIMO(Cursor $pimo_cursor)
    {
        $mmobj_array = new ArrayCollection();
        // Buscamos el numero de objetos multimedia y no el numero de personas en el objeto multimedia
        foreach ($pimo_cursor as $item) {
            $query = $this->dm->find('PumukitSchemaBundle:PersonInMultimediaObject', $item->getId());
            $cursor = $this->dm
                ->createQueryBuilder('PumukitSchemaBundle:MultimediaObject')
                ->field('people_in_multimedia_object')->references($query)
                ->getQuery()->execute();
            foreach ($cursor as $object) {
                $mmobj_array[] = $object;
            }
        }

        return $mmobj_array;
    }

    private function getMultimediaObjectsWithSeries(Series $series)
    {
        $series_object = $this->dm
                ->find('PumukitSchemaBundle:Series', $series->getId());
        $mmobj_series = $this->dm
                    ->createQueryBuilder('PumukitSchemaBundle:MultimediaObject')
                    ->field('series')->references($series_object)
                    ->getQuery()->execute();

        return $mmobj_series;
    }

    private function createBroadcast($broadcastTypeId)
    {
	$broadcast = new Broadcast();
	$broadcast->setName(ucfirst($broadcastTypeId));
	$broadcast->setBroadcastTypeId($broadcastTypeId);
	$broadcast->setPasswd('password');
	if (0 === strcmp(Broadcast::BROADCAST_TYPE_PRI, $broadcastTypeId)){
	  $broadcast->setDefaultSel(true);
	}else{
	  $broadcast->setDefaultSel(false);
	}
	$broadcast->setDescription(ucfirst($broadcastTypeId).' broadcast');

	$this->dm->persist($broadcast);
	$this->dm->flush();

	return $broadcast;
    }
}
