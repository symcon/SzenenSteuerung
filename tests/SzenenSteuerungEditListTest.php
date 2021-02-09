<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungEditListTest extends TestCase
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

    public function testEditVariable()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        $variableA = IPS_CreateVariable(1 /* Integer */);
        $variableB = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($variableA, $sid);
        IPS_SetVariableCustomAction($variableB, $sid);

        //Creating SzenenSteuerungs instance with custom settings
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 1,
            'Targets'    => json_encode([
                [
                    'VariableID' => $variableA,
                    'ID'         => 1
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        //Checking if all settings have been adopted
        $this->assertEquals(1, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(json_encode([['VariableID' => $variableA, 'ID' => 1]]), IPS_GetProperty($iid, 'Targets'));

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        //Save & Recall value for Scene
        SetValue($variableA, 5);
        $intf->SaveScene(1);
        SetValue($variableA, 22);
        $intf->CallScene(1);
        $this->assertEquals(5, GetValue($variableA));

        //Replace variable
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 1,
            'Targets'    => json_encode([
                [
                    'VariableID' => $variableB,
                    'ID'         => 1
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        SetValue($variableA, 22);
        $this->assertEquals(0, GetValue($variableB));
        $intf->CallScene(1);
        $this->assertEquals(22, GetValue($variableA));
        $this->assertEquals(5, GetValue($variableB));
    }

    public function testDeleteVariable()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        $variableA = IPS_CreateVariable(1 /* Integer */);
        $variableB = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($variableA, $sid);
        IPS_SetVariableCustomAction($variableB, $sid);

        //Creating SzenenSteuerungs instance
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 15,
            'Targets'    => json_encode([
                [
                    'VariableID' => $variableA,
                    'ID'         => 1
                ],
                [
                    'VariableID' => $variableB,
                    'ID'         => 2
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        //Save & Recall value for Scene 1
        SetValue($variableA, 10);
        SetValue($variableB, 20);
        $intf->SaveScene(1);
        SetValue($variableA, 42);
        SetValue($variableB, 24);
        $intf->CallScene(1);
        $this->assertEquals(10, GetValue($variableA));
        $this->assertEquals(20, GetValue($variableB));

        //Reset values
        SetValue($variableA, 42);
        SetValue($variableB, 24);

        //Delete variableA
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 15,
            'Targets'    => json_encode([
                [
                    'VariableID' => $variableB,
                    'ID'         => 2
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        //Recall value for Scene 1
        $intf->CallScene(1);
        $this->assertEquals(42, GetValue($variableA));
        $this->assertEquals(20, GetValue($variableB));
    }
}