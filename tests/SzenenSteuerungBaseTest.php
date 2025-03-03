<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungBaseTest extends TestCase
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

    public function testSaveAndLoadSceneData()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        $vid = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid, $sid);

        //Creating SzenenSteuerungs instance with custom settings
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 1,
            'Targets'    => json_encode([
                [
                    'VariableID'   => $vid,
                    'GUID'         => 'guid1'
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        //Checking if all settings have been adopted
        $this->assertEquals(1, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(json_encode([['VariableID' => $vid, 'GUID' => 'guid1']]), IPS_GetProperty($iid, 'Targets'));

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        //Save & Recall value for Scene
        SetValue($vid, 5);
        $intf->SaveScene(1);
        SetValue($vid, 22);
        $intf->CallScene(1);
        $this->assertEquals(5, GetValue($vid));
    }

    public function testManyScenes()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        $vid = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid, $sid);

        //Creating SzenenSteuerungs instance
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 15,
            'Targets'    => json_encode([
                [
                    'VariableID'   => $vid,
                    'GUID'         => 'guid2'
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        //Save & Recall value for Scene 2
        SetValue($vid, 10);
        $intf->SaveScene(2);
        SetValue($vid, 42);
        $intf->CallScene(2);
        $this->assertEquals(10, GetValue($vid));

        //Save & Reecall value for Scene 12
        SetValue($vid, 5);
        $this->assertEquals(5, GetValue($vid));
        $intf->SaveScene(12);
        SetValue($vid, 43);
        $intf->CallScene(12);

        //Verify that recalling Scene 12 does not inerfere with Scene 2 value
        $intf->CallScene(2);
        $this->assertEquals(10, GetValue($vid));
    }
}