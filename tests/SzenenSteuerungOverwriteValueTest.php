<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungOverwriteValueTest extends TestCase
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
    public function testOverwriteValue()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        $vid1 = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid1, $sid);

        //Creating SzenenSteuerungs instance with custom settings
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount'     => 1,
            'OverwriteValue' => false,
            'Targets'        => json_encode([
                [
                    'VariableID'   => $vid1,
                    'GUID'         => 1
                ],
            ])
        ]));
        IPS_ApplyChanges($iid);

        //Checking if all settings have been adopted
        $this->assertEquals(1, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(json_encode([['VariableID' => $vid1, 'GUID' => 1]]), IPS_GetProperty($iid, 'Targets'));

        $intf = IPS\InstanceManager::getInstanceInterface($iid);
        //Set a value and save the scene
        SetValue($vid1, 42);
        $intf->SaveScene(1);
        $this->assertEquals(42, GetValue($vid1));

        //Change the value and call the saved scene
        SetValue($vid1, 12);
        $this->assertEquals(12, GetValue($vid1));
        $intf->CallScene(1);

        //Call the scene again and check if the value updated
        $lastUpdate = IPS_GetVariable($vid1)['VariableUpdated'];
        //Wait at least 2 seconds 1 might not be enough
        sleep(2);
        $intf->CallScene(1);
        $this->assertEquals($lastUpdate, IPS_GetVariable($vid1)['VariableUpdated']);

        //Enable overwriting
        $intf->SetProperty('OverwriteValue', true);
        $intf->ApplyChanges();
        $intf->CallScene(1);
        $this->assertTrue($lastUpdate < IPS_GetVariable($vid1)['VariableUpdated']);
    }
}
