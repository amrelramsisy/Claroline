<?php
/**
 * Created by PhpStorm.
 * User: jorge
 * Date: 24/02/14
 * Time: 11:55
 */

namespace Claroline\CoreBundle\Library;

use Claroline\CoreBundle\Library\Testing\MockeryTestCase;
use Mockery as m;
use Claroline\CoreBundle\Library\Transfert\ConfigurationBuilders\RolesImporter;
use Symfony\Component\Yaml\Yaml;

class RolesImporterTest extends MockeryTestCase
{
    private $om;
    private $importer;

    protected function setUp()
    {
        parent::setUp();

        $this->om = $this->mock('Claroline\CoreBundle\Persistence\ObjectManager');
        $this->importer = new RolesImporter($this->om);
    }

    /**
     * @dataProvider validateProvider
     */
    public function testValidate($path, $isExceptionExpected)
    {
        if ($isExceptionExpected) {
            $this->setExpectedException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
        }

        $data = Yaml::parse(file_get_contents($path));
        $roles['roles'] = $data['roles'];
        $this->importer->validate($roles);
    }

    public function validateProvider()
    {
        return array(
            array(
                'path' => __DIR__.'/../../../Stub/transfert/valid/full/roles01.yml',
                'isExceptionExpected' => false
            ),
            array(
                'path' => __DIR__.'/../../../Stub/transfert/invalid/roles/existing_name.yml',
                'isExceptionExpected' => true
            )
        );
    }
}