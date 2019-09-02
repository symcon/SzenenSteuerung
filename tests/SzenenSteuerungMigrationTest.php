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
        //Createing ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Creating variable to save
        $vid = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid, $sid);
        
        //Setting variable and putting it in serialised in SceneData
        $data = [
            $vid => 7
        ];
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        $vdid = IPS_CreateVariable(3 /* String */);
        IPS_SetIdent($vdid, 'Scene1Data');
        IPS_SetParent($vdid, $iid);
        SetValue($vdid, wddx_serialize_value($data));

        $this->assertEquals($vdid, IPS_GetObjectIDByIdent('Scene1Data', $iid));

        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 1,
            'Targets'    => '[]'
        ]));

        //Create category with linked variable to be transfered
        $cid = IPS_CreateCategory();
        IPS_SetIdent($cid, 'TargetsCategory');
        IPS_SetParent($cid, $iid);
        $lid = IPS_CreateLink();
        IPS_SetLinkTargetID($lid, $vid);

        IPS_ApplyChanges($iid);

        //checks if all unnecessary links/categorys have been deleted
        $this->assertEquals(1, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(false, IPS_VariableExists($vdid));
        $this->assertEquals(false, IPS_LinkExists($lid));
        //Test if data was transfered
        $intf = IPS\InstanceManager::getInstanceInterface($iid);
        $intf->CallScene(1);
        $this->assertEquals(7, GetValue($vid));
    }
}