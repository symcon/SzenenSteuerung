<?php

declare(strict_types=1);
include_once __DIR__ . '/attributes.php';
class SceneControl extends IPSModule
{
    use Attributes;
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('SceneCount', 5);
        $this->RegisterPropertyString('Targets', '[]');
        $this->RegisterPropertyBoolean('OverwriteValue', false);
        //Attributes
        $this->RegisterAttributeString('SceneData', '[]');
        //Timer
        $this->RegisterTimer('UpdateTimer', 0, 'SZS_UpdateActive($_IPS[\'TARGET\']);');

        $this->RegisterVariableString('ActiveScene', $this->Translate('Active Scene'), '', -1);
        if (!IPS_VariableProfileExists('SZS.SceneControl')) {
            IPS_CreateVariableProfile('SZS.SceneControl', 1);
            IPS_SetVariableProfileValues('SZS.SceneControl', 1, 2, 0);
            //IPS_SetVariableProfileIcon("SZS.SceneControl", "");
            IPS_SetVariableProfileAssociation('SZS.SceneControl', 1, $this->Translate('Save'), '', -1);
            IPS_SetVariableProfileAssociation('SZS.SceneControl', 2, $this->Translate('Call'), '', -1);
        }
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $targets = json_decode($this->ReadPropertyString('Targets'), true);

        //Add GUID if none set
        $needsReload = false;
        foreach ($targets as $index => $target) {
            if (!isset($targets[$index]['GUID'])) {
                $targets[$index]['GUID'] = $this->generateGUID();
                $needsReload = true;
            }
        }

        //Create Scene variables
        $sceneCount = $this->ReadPropertyInteger('SceneCount');
        for ($i = 1; $i <= $sceneCount; $i++) {
            $variableID = $this->RegisterVariableInteger('Scene' . $i, sprintf($this->Translate('Scene %d'), $i), 'SZS.SceneControl');
            $this->EnableAction('Scene' . $i);
            SetValue($variableID, 2);
        }

        $sceneData = json_decode($this->ReadAttributeString('SceneData'));

        //If older versions contain errors regarding SceneData SceneControl would become unusable otherwise, even in fixed versions
        if (!is_array($sceneData)) {
            $sceneData = [];
        }

        //Preparing SceneData for later use
        $sceneCount = $this->ReadPropertyInteger('SceneCount');

        for ($i = 1; $i <= $sceneCount; $i++) {
            if (!array_key_exists($i - 1, $sceneData)) {
                $sceneData[$i - 1] = new stdClass();
            }
        }

        //Deleting surplus data in SceneData
        $sceneData = array_slice($sceneData, 0, $sceneCount);
        $this->WriteAttributeString('SceneData', json_encode($sceneData));

        //Deleting surplus variables
        for ($i = $sceneCount + 1; ; $i++) {
            if (@$this->GetIDForIdent('Scene' . $i)) {
                $this->UnregisterVariable('Scene' . $i);
            } else {
                break;
            }
        }

        //Transfer variableIDs to IDs
        $variableGUIDs = [];
        foreach ($targets as $target) {
            $variableGUIDs[$target['VariableID']] = $target['GUID'];
        }
        $scenes = json_decode($this->ReadAttributeString('SceneData'), true);
        foreach ($scenes as $index => $scene) {
            foreach ($scene as $variableID => $value) {
                if (array_key_exists($variableID, $variableGUIDs)) {
                    unset($scenes[$index][$variableID]);
                    $scenes[$index][$variableGUIDs[$variableID]] = $value;
                }
            }
        }
        $this->WriteAttributeString('SceneData', json_encode($scenes));

        //Reload if there were any changes
        if ($needsReload) {
            IPS_SetProperty($this->InstanceID, 'Targets', json_encode($targets));
            IPS_ApplyChanges($this->InstanceID);
            return;
        }

        //Add references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        foreach ($targets as $target) {
            $this->RegisterReference($target['VariableID']);
        }

        //Unregister all messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }

        //Register messages if neccessary
        foreach ($targets as $target) {
            $this->RegisterMessage($target['VariableID'], VM_UPDATE);
        }

        //Set active scene
        $this->SetValue('ActiveScene', $this->getSceneName($this->GetActiveScene()));
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message == VM_UPDATE && json_decode($this->GetBuffer('UpdateActive'))) {
            $this->SetValue('ActiveScene', $this->getSceneName($this->GetActiveScene()));
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Value) {
            case '1':
                $this->SaveValues($Ident);
                $this->SetValue('ActiveScene', $this->getSceneName($this->GetActiveScene()));
                break;
            case '2':
                $this->SetBuffer('UpdateActive', json_encode(false));
                $this->SetValue('ActiveScene', sprintf($this->Translate("'%s' is called"), IPS_GetName($this->GetIDForIdent($Ident))));
                $this->SetTimerInterval('UpdateTimer', 5 * 1000);
                $this->CallValues($Ident);

                break;
            default:
                throw new Exception('Invalid action');
        }
    }

    public function CallScene(int $SceneNumber)
    {
        $this->CallValues("Scene$SceneNumber");
    }

    public function SaveScene(int $SceneNumber)
    {
        $this->SaveValues("Scene$SceneNumber");
    }

    public function GetActiveScene()
    {
        $scenes = json_decode($this->ReadAttributeString('SceneData'), true);
        $targets = json_decode($this->ReadPropertyString('Targets'), true);
        $sceneCount = $this->ReadPropertyInteger('SceneCount');
        $sceneID = -1;
        for ($i = 0; $i < $sceneCount; $i++) {
            foreach ($scenes[$i] as $guid => $value) {
                $variableID = $this->getVariable($guid);
                $sceneID = $i;
                if (IPS_VariableExists($variableID)) {
                    if (GetValue($variableID) != $value) {
                        $sceneID = -1;
                        break;
                    }
                }
            }
            if ($sceneID != -1) {
                break;
            }
        }
        //The 'sceneID' starts at 1
        return $sceneID + 1;
    }

    public function UpdateActive()
    {
        $this->SetTimerInterval('UpdateTimer', 0);
        $this->SetValue('ActiveScene', $this->getSceneName($this->GetActiveScene()));
        $this->SetBuffer('UpdateActive', json_encode(true));
    }

    public function AddVariable(IPSList $Targets)
    {
        $this->SendDebug('New Value', json_encode($Targets), 0);
        $form = json_decode($this->GetConfigurationForm(), true);
        $this->UpdateFormField('Targets', 'columns', json_encode($form['elements'][2]['columns']));
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $form['elements'][2]['columns'][0]['add'] = $this->generateGUID();

        // //generate the Lists for the action section
        $targets = json_decode($this->ReadPropertyString('Targets'), true);
        if (count($targets) === 0) {
            return json_encode($form);
        }

        $actions = [['type' => 'Label', 'caption' => $this->translate('Set Scenes')]];
        $sceneCount = $this->ReadPropertyInteger('SceneCount');
        $sceneData = json_decode($this->ReadAttributeString('SceneData'), true);
        for ($i = 1; $i <= $sceneCount; $i++) {
            $selectTargets = [];
            $dataNames = [];
            $ignoreNames = [];
            $sceneGuids = [];

            foreach ($targets as $key => $value) {
                $this->SendDebug($key, print_r($value, true), 0);
                $variableID = $value['VariableID'];
                if (!IPS_VariableExists($variableID)) {
                    continue; // Maybe alternativ field
                }
                $valueExists = array_key_exists($i - 1, $sceneData) && array_key_exists($value['GUID'], $sceneData[$i - 1]);
                $targetValue = json_encode($valueExists ? (is_array($sceneData[$i - 1][$value['GUID']]) ? $sceneData[$i - 1][$value['GUID']]['value'] : $sceneData[$i - 1][$value['GUID']]) : GetValue($variableID));
                $ignoreValue = $valueExists ? (is_array($sceneData[$i - 1][$value['GUID']]) ? $sceneData[$i - 1][$value['GUID']]['ignore'] : false) : false;
                $selectTargets[] =
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Set Value',
                                        'value'   => false,
                                    ],
                                    [
                                        'caption' => 'Ignore',
                                        'value'   => true,
                                    ]
                                ],
                                'value'    => $ignoreValue,
                                'caption'  => IPS_GetLocation($variableID),
                                'name'     => 'Scene' . $i . 'ID' . $variableID . 'IGNORE',
                                'onChange' => 'SZS_UIUpdateVisibility($id, ' . '"Scene' . $i . 'ID' . $variableID . '", $Scene' . $i . 'ID' . $variableID . 'IGNORE);'

                            ],
                            [
                                'type'       => 'SelectValue',
                                'name'       => 'Scene' . $i . 'ID' . $variableID,
                                'value'      => $targetValue,
                                'variableID' => $variableID,
                                'visible'    => !$ignoreValue,
                            ]
                        ]
                    ];
                $dataNames[] = '$Scene' . $i . 'ID' . $variableID;
                $ignoreNames[] = '$Scene' . $i . 'ID' . $variableID . 'IGNORE';
                $sceneGuids[] = $value['GUID'];
            }
            $selectTargets[] = [
                'type'       => 'Button',
                'caption'    => $this->Translate('Save'),
                'onClick'    => 'SZS_SaveSceneEx($id, ' . $i . ', ' . json_encode($dataNames, JSON_UNESCAPED_SLASHES) . ', ' . json_encode($ignoreNames, JSON_UNESCAPED_SLASHES) . ', ' . json_encode($sceneGuids) . ');'
            ];
            $actions[$i] = [
                'type'    => 'ExpansionPanel',
                'name'    => 'Scene' . $i,
                'caption' => $this->getSceneName($i),
                'items'   => $selectTargets
            ];
        }

        $form['actions'] = $actions;
        $this->SendDebug('actions', json_encode($actions), 0);
        return json_encode($form);
    }

    public function SaveSceneEx(int $sceneNumber, array $sceneValues, array $ignoreValues, array $sceneGuids): bool
    {
        $unsavedData = array_combine($sceneGuids, $sceneValues);
        $ignoreList = array_combine($sceneGuids, $ignoreValues);
        //fix datatype
        foreach ($unsavedData as $guid => $value) {
            $id = $this->getVariable($guid);
            if (!IPS_VariableExists($id)) {
                continue;
            }
            $type = IPS_GetVariable($id)['VariableType'];
            $value = match ($type) {
                0 => $value == 'true',
                1 => intval($value),
                2 => floatval($value),
                3 => trim($value, '"'),
            };
            $unsavedData[$guid] = ['value' => $value, 'ignore' => boolval($ignoreList[$guid])];
        }
        $sceneData = json_decode($this->ReadAttributeString('SceneData'), true);

        // set the value in the correct scene
        $sceneData[$sceneNumber - 1] = $unsavedData;
        $this->WriteAttributeString('SceneData', json_encode($sceneData));
        return true;
    }

    public function UIUpdateVisibility(string $Field, bool $Hidden)
    {
        $this->UpdateFormField($Field, 'visible', !$Hidden);
    }

    private function generateGUID()
    {
        return sprintf('{%04X%04X-%04X-%04X-%04X-%04X%04X%04X}', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    private function getVariable(string $guid)
    {
        $targets = json_decode($this->ReadPropertyString('Targets'), true);
        foreach ($targets as $target) {
            if ($target['GUID'] == $guid) {
                return $target['VariableID'];
            }
        }
        return 0;
    }

    private function getSceneName(int $sceneID)
    {
        if ($sceneID != 0) {
            return IPS_GetName($this->GetIDForIdent("Scene$sceneID"));
        } else {
            return $this->Translate('Unknown');
        }
    }

    private function SaveValues(string $sceneIdent)
    {
        $data = [];

        $targets = json_decode($this->ReadPropertyString('Targets'), true);

        $sceneData = json_decode($this->ReadAttributeString('SceneData'), true);
        $i = (int) filter_var($sceneIdent, FILTER_SANITIZE_NUMBER_INT);
        foreach ($targets as $target) {
            $VarID = $target['VariableID'];
            if (!IPS_VariableExists($VarID)) {
                continue;
            }
            // We need to get the ignore value from the attribute to not override it
            if (isset($sceneData[$i - 1][$target['GUID']])) {
                if (is_array($sceneData[$i - 1][$target['GUID']])) {
                    $data[$target['GUID']] = ['value' => GetValue($VarID), 'ignore' => $sceneData[$i - 1][$target['GUID']]['ignore']];
                    continue;
                }
            }
            // Fallback for new values and transferring new ones
            $data[$target['GUID']] = ['value' => GetValue($VarID), 'ignore' => false];
        }

        $sceneData[$i - 1] = $data;

        $this->WriteAttributeString('SceneData', json_encode($sceneData));
    }

    private function CallValues(string $sceneIdent)
    {
        $sceneData = json_decode($this->ReadAttributeString('SceneData'), true);

        $i = (int) filter_var($sceneIdent, FILTER_SANITIZE_NUMBER_INT);

        $data = $sceneData[$i - 1];

        if (count($data) > 0) {
            foreach ($data as $guid => $value) {
                if (is_array($value)) {
                    if ($value['ignore']) {
                        continue;
                    }
                    $value = $value['value'];
                }
                $id = $this->getVariable($guid);
                if (IPS_VariableExists($id)) {
                    $v = IPS_GetVariable($id);
                    if (!$this->ReadPropertyBoolean('OverwriteValue') && GetValue($id) == $value) {
                        continue;
                    }
                    if ($v['VariableCustomAction'] > 0) {
                        $actionID = $v['VariableCustomAction'];
                    } else {
                        $actionID = $v['VariableAction'];
                    }
                    //Skip this device if we do not have a proper id
                    if ($actionID < 10000) {
                        continue;
                    }

                    RequestAction($id, $value);
                }
            }
        } else {
            echo $this->Translate('No saved data for this Scene');
        }
    }
}
