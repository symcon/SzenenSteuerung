<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungActiveSceneTest extends TestCase
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
            'SceneCount' => 2,
            'Targets'    => json_encode([
                [
                    'VariableID'   => $vid,
                    'GUID'         => 'guid1'
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        //Checking if all settings have been adopted
        $this->assertEquals(2, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(json_encode([['VariableID' => $vid, 'GUID' => 'guid1']]), IPS_GetProperty($iid, 'Targets'));

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        //Save 2 distinct scenes
        SetValue($vid, 5);
        $intf->SaveScene(1);
        SetValue($vid, 10);
        $intf->SaveScene(2);

        //Call the scene and check if the active scene matches
        $intf->CallScene(1);
        $this->assertEquals(1, $intf->GetActiveScene());
        $intf->CallScene(2);
        $this->assertEquals(2, $intf->GetActiveScene());

        //Check if the active scene matches if the change was not made by the module itself
        SetValue($vid, 5);
        $this->assertEquals(1, $intf->GetActiveScene());

        //Check if the variable is updated accordingly
        $active = IPS_GetObjectIDByIdent('ActiveScene', $iid);
        $intf->UpdateActive(); // This will be called by the update timer
        $this->assertEquals('Scene 1', GetValue($active));

        //Check if unknown is properly set
        SetValue($vid, 7);
        $this->assertEquals(0, $intf->GetActiveScene());
        $intf->UpdateActive(); // This will be called by the update timer
        $this->assertEquals('Unknown', GetValue($active));
    }
}