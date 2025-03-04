<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';
include_once __DIR__ . '/stubs/Console.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungIgnoreMigrationTest extends TestCase
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
    public function testIgnoreMigration()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        $vid1 = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid1, $sid);
        $vid2 = IPS_CreateVariable(1 /* Integer */);
        IPS_SetVariableCustomAction($vid2, $sid);

        //Creating SzenenSteuerungs instance with custom settings
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 1,
            'Targets'    => json_encode([
                [
                    'VariableID'   => $vid1,
                    'GUID'         => 'guid1'
                ],
                [
                    'VariableID'   => $vid2,
                    'GUID'         => 'guid2'
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        //Checking if all settings have been adopted
        $this->assertEquals(1, IPS_GetProperty($iid, 'SceneCount'));
        $this->assertEquals(json_encode([['VariableID' => $vid1, 'GUID' => 'guid1'], ['VariableID' => $vid2, 'GUID' => 'guid2']]), IPS_GetProperty($iid, 'Targets'));

        $intf = IPS\InstanceManager::getInstanceInterface($iid);
        $oldData = [
            //Scene 1
            [
                'guid1' => 42,
                'guid2' => 24,
            ]
        ];
        $intf->SetAttribute('SceneData', json_encode($oldData));
        //Execute old scene
        $intf->CallScene(1);
        $this->assertEquals(42, GetValue($vid1));
        $this->assertEquals(24, GetValue($vid2));
        $this->assertEquals(json_encode($oldData), $intf->GetAttribute('SceneData'));

        //Set new values
        SetValue($vid1, 12);
        SetValue($vid2, 34);
        //Saving should add ignore fields
        $intf->SaveScene(1);
        $newData = [
            [
                'guid1' => ['value' => 12, 'ignore' => false],
                'guid2' => ['value' => 34, 'ignore' => false],
            ]
        ];
        $intf->CallScene(1);
        $this->assertEquals(json_encode($newData), $intf->GetAttribute('SceneData'));
        SetValue($vid1, 1);
        SetValue($vid2, 2);
        $newData[0]['guid1']['ignore'] = true;
        $intf->SetAttribute('SceneData', json_encode($newData));
        $intf->CallScene(1);
        $this->assertEquals(1, GetValue($vid1));
        $this->assertEquals(34, GetValue($vid2));
    }
}
