<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungIdMigrationTest extends TestCase
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
    public function testIDMigration()
    {
        //Createing ActionScript
        $actionsScript = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($actionsScript, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Creating variable to save
        $targetVariableOne = IPS_CreateVariable(0 /* Bool */);
        $targetVariableTwo = IPS_CreateVariable(0 /* Bool */);
        IPS_SetVariableCustomAction($targetVariableOne, $actionsScript);
        IPS_SetVariableCustomAction($targetVariableTwo, $actionsScript);

        $instanceID = IPS_CreateInstance($this->szenenSteuerungID);

        IPS_SetConfiguration($instanceID, json_encode([
            'SceneCount' => 2,
            'Targets'    => json_encode([
                [
                    'VariableID' => $targetVariableOne,
                    'ID'         => 0
                ],
                [
                    'VariableID' => $targetVariableTwo,
                    'ID'         => 0
                ]
            ])
        ]));

        $sceneData = [
            [
                $targetVariableOne => true,
                $targetVariableTwo => true
            ],
            [
                $targetVariableOne => false,
                $targetVariableTwo => false
            ]
        ];
        $intf = IPS\InstanceManager::getInstanceInterface($instanceID);
        // $intf->WriteAttributeString('SceneData', json_encode($sceneData));
        $intf->SetAttribute('SceneData', json_encode($sceneData));
        IPS_ApplyChanges($instanceID);
        // echo $intf->GetAttribute('SceneData');
        $expectedTargets = [
            ['VariableID' => $targetVariableOne, 'ID' => 1],
            ['VariableID' => $targetVariableTwo, 'ID' => 2]
        ];
        $this->assertEquals(json_encode($expectedTargets), IPS_GetConfiguration($instanceID)['Targets']);
        $expectedSceneData = [
            [1 => true, 2 => true],
            [1 => false, 2 => false]
        ];
        $this->assertEquals(json_encode($expectedSceneData), $intf->GetAttribute('SceneData'));
    }
}