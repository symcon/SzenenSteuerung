<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungTest extends TestCase
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
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        $vid = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid, $sid);
        SetValue($vid, 42);

        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 1,
            'Targets'    => json_encode([
                [
                    'VariableID' => $vid
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        $this->assertEquals(1, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(json_encode([['VariableID' => $vid]]), IPS_GetProperty($iid, 'Targets'));

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        SetValue($vid, 5);

        $intf->SaveScene(1);
        $this->assertEquals(5, GetValue($vid));

        SetValue($vid, 22);
        $intf->CallScene(1);

        $this->assertEquals(5, GetValue($vid));
    }

    public function testManyScenes()
    {
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        $vid = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid, $sid);

        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 15,
            'Targets'    => json_encode([
                [
                    'VariableID' => $vid
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        $this->assertEquals(15, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(json_encode([['VariableID' => $vid]]), IPS_GetProperty($iid, 'Targets'));

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        //Scenen2
        SetValue($vid, 10);

        $this->assertEquals(10, GetValue($vid));
        $intf->SaveScene(2);

        SetValue($vid, 42);
        $intf->CallScene(2);

        $this->assertEquals(10, GetValue($vid));
        //Scene 2

        //Scenen12
        SetValue($vid, 5);

        $this->assertEquals(5, GetValue($vid));
        $intf->SaveScene(12);

        SetValue($vid, 43);
        $intf->CallScene(12);

        $this->assertEquals(5, GetValue($vid));
        //Scene 12

        //expecting 10 because 10 should be saved in Scene 2
        $intf->CallScene(2);
        $this->assertEquals(10, GetValue($vid));
    }
}