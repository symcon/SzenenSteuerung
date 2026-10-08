<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';
include_once __DIR__ . '/stubs/Console.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungEscapedJSONRepairTest extends TestCase
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

    public function testRepairEscapedJSON()
    {
        //Setting up variables with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Color variable holding a JSON value
        $colorVariable = IPS_CreateVariable(3 /* String */);
        IPS_SetVariableCustomAction($colorVariable, $sid);
        SetValue($colorVariable, '{"r":0,"g":0,"b":255}');
        //Text variable holding a plain string
        $textVariable = IPS_CreateVariable(3 /* String */);
        IPS_SetVariableCustomAction($textVariable, $sid);
        SetValue($textVariable, 'Küche');
        //Color variable which got the escaped value by calling a broken scene
        $brokenColorVariable = IPS_CreateVariable(3 /* String */);
        IPS_SetVariableCustomAction($brokenColorVariable, $sid);
        SetValue($brokenColorVariable, $this->saveInOldForm('{"x":0.4,"y":0.5}', 1));
        //Color variable without a value, e.g. as the lamp is not reachable
        $emptyColorVariable = IPS_CreateVariable(3 /* String */);
        IPS_SetVariableCustomAction($emptyColorVariable, $sid);

        //Creating SzenenSteuerungs instance with custom settings
        $iid = IPS_CreateInstance($this->szenenSteuerungID);
        IPS_SetConfiguration($iid, json_encode([
            'SceneCount' => 3,
            'Targets'    => json_encode([
                [
                    'VariableID'   => $colorVariable,
                    'GUID'         => 'guid1'
                ],
                [
                    'VariableID'   => $textVariable,
                    'GUID'         => 'guid2'
                ],
                [
                    'VariableID'   => $brokenColorVariable,
                    'GUID'         => 'guid3'
                ],
                [
                    'VariableID'   => $emptyColorVariable,
                    'GUID'         => 'guid4'
                ]
            ])
        ]));
        IPS_ApplyChanges($iid);

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        $red = '{"r":255,"g":0,"b":0}';
        $green = '{"r":0,"g":255,"b":0}';
        $blue = '{"r":0,"g":0,"b":255}';
        $xy = '{"x":0.3,"y":0.6}';
        $brokenData = [
            //Scene 1
            [
                'guid1' => ['value' => $this->saveInOldForm($red, 1), 'ignore' => false],
                'guid2' => ['value' => $this->saveInOldForm('Küche', 1), 'ignore' => false],
                'guid3' => ['value' => $this->saveInOldForm($xy, 1), 'ignore' => false],
                'guid4' => ['value' => $this->saveInOldForm($xy, 2), 'ignore' => false],
            ],
            //Scene 2
            [
                'guid1' => ['value' => $this->saveInOldForm($green, 3), 'ignore' => true],
                'guid2' => ['value' => 'C:\new', 'ignore' => false],
            ],
            //Scene 3
            [
                'guid1' => ['value' => $blue, 'ignore' => false],
                'guid2' => ['value' => 'say "hi"', 'ignore' => false],
            ]
        ];
        $intf->SetAttribute('SceneData', json_encode($brokenData));
        IPS_ApplyChanges($iid);

        //Only the escaped color values are repaired
        $repairedData = $brokenData;
        $repairedData[0]['guid1']['value'] = $red;
        $repairedData[0]['guid3']['value'] = $xy;
        $repairedData[0]['guid4']['value'] = $xy;
        $repairedData[1]['guid1']['value'] = $green;
        $this->assertEquals($repairedData, json_decode($intf->GetAttribute('SceneData'), true));

        //Repairing again does not change anything
        IPS_ApplyChanges($iid);
        $this->assertEquals($repairedData, json_decode($intf->GetAttribute('SceneData'), true));

        //Calling the scene sets the repaired colors
        $intf->CallScene(1);
        $this->assertEquals($red, GetValue($colorVariable));
        $this->assertEquals($xy, GetValue($brokenColorVariable));
        $this->assertEquals($xy, GetValue($emptyColorVariable));
    }

    public function testNoRepairForTextVariable()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        $vid = IPS_CreateVariable(3 /* String */);
        IPS_SetVariableCustomAction($vid, $sid);
        SetValue($vid, 'some text');

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

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        //Escaped JSON is kept as the variable holds plain text
        $data = [
            [
                'guid1' => ['value' => '{\"a\":1}', 'ignore' => false],
            ]
        ];
        $intf->SetAttribute('SceneData', json_encode($data));
        IPS_ApplyChanges($iid);
        $this->assertEquals($data, json_decode($intf->GetAttribute('SceneData'), true));
    }

    //Simulates saving a string value in the configuration form before version 1.8
    private function saveInOldForm(string $value, int $times): string
    {
        for ($i = 0; $i < $times; $i++) {
            $value = trim(json_encode($value), '"');
        }
        return $value;
    }
}
