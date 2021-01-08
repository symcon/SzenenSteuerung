<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungMigrationTest extends TestCase
{
    private $szenenSteuerungID = '{87F46796-CC43-442D-94FD-AAA0BD8D9F54}';

    public function setUp(): void
    {
        //Reset
        IPS\Kernel::reset();
        //Register our library we need for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        parent::setUp();
    }
    //Testing if the migration from wddx and data variables works correctly
    public function testMigration()
    {
        //Skip this test if the function doesn't exist anymore
        if (!function_exists('wddx_serialize_value')) {
            $this->markTestSkipped('needs PHP 7.3 or lower');
        }
        //Createing ActionScript
        $actionsScript = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($actionsScript, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Creating variable to save
        $targetVariableID = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($targetVariableID, $actionsScript);

        //Setting variable and putting it in serialised in SceneData
        $data = [
            $targetVariableID => 7
        ];
        $instanceID = IPS_CreateInstance($this->szenenSteuerungID);
        $sceneDataID = IPS_CreateVariable(3 /* String */);
        IPS_SetIdent($sceneDataID, 'Scene1Data');
        IPS_SetParent($sceneDataID, $instanceID);
        SetValue($sceneDataID, wddx_serialize_value($data));

        $this->assertEquals($sceneDataID, IPS_GetObjectIDByIdent('Scene1Data', $instanceID));

        IPS_SetConfiguration($instanceID, json_encode([
            'SceneCount' => 1,
            'Targets'    => '[]'
        ]));

        //Create Targets category
        $targetCategoryID = IPS_CreateCategory();
        IPS_SetIdent($targetCategoryID, 'Targets');
        IPS_SetParent($targetCategoryID, $instanceID);
        //Create link to target variable
        $linkID = IPS_CreateLink();
        IPS_SetLinkTargetID($linkID, $targetVariableID);
        IPS_SetParent($linkID, $targetCategoryID);

        IPS_ApplyChanges($instanceID);

        //checks if all unnecessary links/categorys have been deleted
        $this->assertEquals(1, IPS_GetProperty($instanceID, 'SceneCount'));
        $this->assertEquals(false, IPS_VariableExists($sceneDataID));
        $this->assertEquals(false, IPS_LinkExists($linkID));
        //Test if data was transfered
        $intf = IPS\InstanceManager::getInstanceInterface($instanceID);
        $intf->CallScene(1);
        $this->assertEquals(7, GetValue($targetVariableID));
    }
}