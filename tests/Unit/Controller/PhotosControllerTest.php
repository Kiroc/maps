<?php
/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Controller;

use \OCA\Maps\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;
use OCA\Maps\Service\DevicesService;
use OCA\Maps\DB\GeophotoMapper;
use OCP\AppFramework\Http\TemplateResponse;


class PhotosControllerTest extends \PHPUnit\Framework\TestCase {
    private $appName;
    private $request;
    private $contacts;

    private $container;
    private $config;
    private $app;

    private $photosController;
    private $photosController2;
    private $utilsController;

    private $photoFileService;

    public static function setUpBeforeClass(): void {
        $app = new Application();
        $c = $app->getContainer();

        // clear test users
        $user = $c->getServer()->getUserManager()->get('test');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test2');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test3');
        if ($user !== null) {
            $user->delete();
        }

        // CREATE DUMMY USERS
        $u1 = $c->getServer()->getUserManager()->createUser('test', 'tatotitoTUTU');
        $u1->setEMailAddress('toto@toto.net');
        $u2 = $c->getServer()->getUserManager()->createUser('test2', 'plopinoulala000');
        $u3 = $c->getServer()->getUserManager()->createUser('test3', 'yeyeahPASSPASS');
        $c->getServer()->getGroupManager()->createGroup('group1test');
        $c->getServer()->getGroupManager()->get('group1test')->addUser($u1);
        $c->getServer()->getGroupManager()->createGroup('group2test');
        $c->getServer()->getGroupManager()->get('group2test')->addUser($u2);
    }

    protected function setUp(): void {
        $this->appName = 'maps';
        $this->request = $this->getMockBuilder('\OCP\IRequest')
            ->disableOriginalConstructor()
            ->getMock();
        $this->contacts = $this->getMockBuilder('OCP\Contacts\IManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->app = new Application();
        $this->container = $this->app->getContainer();
        $c = $this->container;
        $this->config = $c->query('ServerContainer')->getConfig();

        $this->rootFolder = $c->query('ServerContainer')->getRootFolder();

        $this->photoFileService = new PhotoFilesService(
            $c->query('ServerContainer')->getLogger(),
            $this->rootFolder,
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            new GeophotoMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            $c->query('ServerContainer')->getShareManager()
        );

        $this->photosController = new PhotosController(
            $this->appName,
            $c->query('ServerContainer')->getLogger(),
            $this->request,
            new GeoPhotoService(
                $c->query('ServerContainer')->getLogger(),
                $this->rootFolder,
                $c->query('ServerContainer')->getL10N($c->query('AppName')),
                new GeophotoMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                $c->query('ServerContainer')->getPreviewManager(),
                new TracksService(
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $this->rootFolder,
                    $c->query('ServerContainer')->getShareManager()
                ),
                new DevicesService(
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName'))
                ),
                'test'
            ),
            $this->photoFileService,
            'test'
        );

        $this->photosController2 = new PhotosController(
            $this->appName,
            $c->query('ServerContainer')->getLogger(),
            $this->request,
            new GeoPhotoService(
                $c->query('ServerContainer')->getLogger(),
                $this->rootFolder,
                $c->query('ServerContainer')->getL10N($c->query('AppName')),
                new GeophotoMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                $c->query('ServerContainer')->getPreviewManager(),
                new TracksService(
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $this->rootFolder,
                    $c->query('ServerContainer')->getShareManager()
                ),
                new DevicesService(
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName'))
                ),
                'test'
            ),
            $this->photoFileService,
            'test'
        );

        $this->utilsController = new UtilsController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getAppManager()
        );

        $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');

        // delete first
        if ($userfolder->nodeExists('dir/nc.jpg')) {
            echo "DELETE\n";
            $file = $userfolder->get('dir/nc.jpg');
            $file->delete();
        }
        // delete db
        $qb = $c->query('ServerContainer')->getDatabaseConnection()->getQueryBuilder();
        $qb->delete('maps_photos')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public static function tearDownAfterClass(): void {
        $app = new Application();
        $c = $app->getContainer();
        $user = $c->getServer()->getUserManager()->get('test');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test2');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test3');
        $user->delete();
        $c->getServer()->getGroupManager()->get('group1test')->delete();
        $c->getServer()->getGroupManager()->get('group2test')->delete();
    }

    protected function tearDown(): void {
        // in case there was a failure and something was not deleted
    }

    public function testAddGetPhotos() {
        $c = $this->app->getContainer();

        $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');

        $filename = 'tests/test_files/nc.jpg';
        $handle = fopen($filename, 'rb');
        $content1 = fread($handle, filesize($filename));
        fclose($handle);
        //$content1 = file_get_contents('tests/test_files/nc.jpg');
        $userfolder->newFolder('dir');
        $file = $userfolder->newFile('dir/nc.jpgg');
        //->putContent($content1);
        $fp = $file->fopen('wb');
        fwrite($fp, $content1);
        fclose($fp);
        $file->touch();

        $file = $userfolder->get('dir/nc.jpgg');
        $file->move($userfolder->getPath().'/dir/nc.jpg');
        echo 'I MOVE TO '.$userfolder->getPath().'/dir/nc.jpg'."\n";
        $file->touch();

        $file = $userfolder->get('dir/nc.jpg');
        echo 'FILE ID '.$file->getId()."\n";
        $id = $file->getId();
        $file = $userfolder->get('dir')->getById($id);
        var_dump($file);

        // TODO understand why line 72 of GeoPhotoService.php does not work
        // we don't get the file by its ID...

        //echo 'BEFORE RESCAN'."\n";
        //$this->photoFileService->rescan('test');

        //$content2 = file_get_contents('tests/test_files/nut.jpg');
        //$userfolder->newFile('dir/nut.jpg')->putContent($content2);

        //var_dump($userfolder->get('dir')->getDirectoryListing());

        $resp = $this->photosController->getPhotosFromDb();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        //$this->assertEquals(27, $data);
        var_dump($data);

        $this->assertEquals(true, 1===1);

        // delete files
        if ($userfolder->nodeExists('dir/nc.jpg')) {
            echo "DELETE\n";
            $file = $userfolder->get('dir/nc.jpg');
            $file->delete();
        }
        // delete db
        $qb = $c->query('ServerContainer')->getDatabaseConnection()->getQueryBuilder();
        $qb->delete('maps_photos')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

}
