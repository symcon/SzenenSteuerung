<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';
include_once __DIR__ . '/stubs/Console.php';

use PHPUnit\Framework\TestCase;

class SzenenSteuerungSaveSceneExTest extends TestCase
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

    public function testSaveStringValuesFromForm()
    {
        //Setting up a variable with ActionScript
        $sid = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($sid, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        $vid = IPS_CreateVariable(3 /* String */);
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

        $intf = IPS\InstanceManager::getInstanceInterface($iid);

        $values = [
            'plain',
            '{"r":255,"g":0}',
            'Küche',
            'a/b',
            'say "hi"',
            '"quoted"',
            'back\\slash',
            "line\nbreak",
            ''
        ];
        foreach ($values as $value) {
            //Enter the value in the form and save, the SelectValue passes it JSON encoded
            $intf->SaveSceneEx(1, [json_encode($value)], [false], ['guid1']);
            $this->assertSceneValue($intf, $vid, $value, 'Save entered value');

            //Open the form again and save without changes, the value must not change
            $formValue = $this->getSelectValue($intf->GetConfigurationForm(), 'Scene1ID' . $vid);
            $this->assertEquals(json_encode($value), $formValue);
            $intf->SaveSceneEx(1, [$formValue], [false], ['guid1']);
            $this->assertSceneValue($intf, $vid, $value, 'Save unchanged form');
        }
    }

    private function assertSceneValue($intf, int $vid, string $value, string $step)
    {
        SetValue($vid, 'other');
        $intf->CallScene(1);
        $this->assertEquals($value, GetValue($vid), "$step for " . json_encode($value));
    }

    private function getSelectValue(string $form, string $name)
    {
        $search = function ($elements) use (&$search, $name)
        {
            foreach ($elements as $element) {
                if (($element['name'] ?? '') === $name && $element['type'] === 'SelectValue') {
                    return $element['value'];
                }
                if (isset($element['items'])) {
                    $value = $search($element['items']);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }
            return null;
        };
        $value = $search(json_decode($form, true)['actions']);
        $this->assertNotNull($value, "SelectValue $name not found");
        return $value;
    }
}
