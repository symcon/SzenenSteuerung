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

        //Transfer data from Target Category(legacy) to recent List
        if ($targets == []) {
            $targetCategoryID = @$this->GetIDForIdent('Targets');

            if ($targetCategoryID) {
                foreach (IPS_GetChildrenIDs($targetCategoryID) as $childID) {
                    $targetID = IPS_GetLink($childID)['TargetID'];
                    $line = [
                        'VariableID' => $targetID
                    ];
                    array_push($targets, $line);
                    IPS_DeleteLink($childID);
                }

                IPS_DeleteCategory($targetCategoryID);
                $needsReload = true;
            }
        }

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

        //Getting data from legacy SceneData to put them in SceneData attribute (including wddx, JSON)
        for ($i = 1; $i <= $sceneCount; $i++) {
            $sceneDataID = @$this->GetIDForIdent('Scene' . $i . 'Data');
            if ($sceneDataID) {
                $decodedSceneData = null;
                if (function_exists('wddx_deserialize')) {
                    $decodedSceneData = wddx_deserialize(GetValue($sceneDataID));
                }

                if ($decodedSceneData == null) {
                    $decodedSceneData = json_decode(GetValue($sceneDataID));
                }

                if ($decodedSceneData) {
                    $sceneData[$i - 1] = $decodedSceneData;
                }
                $this->UnregisterVariable('Scene' . $i . 'Data');
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

    public function AddVariable($Targets)
    {
        $this->SendDebug('New Value', json_encode($Targets), 0);
        $form = json_decode($this->GetConfigurationForm(), true);
        $this->UpdateFormField('Targets', 'columns', json_encode($form['elements'][1]['columns']));
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $form['elements'][1]['columns'][0]['add'] = $this->generateGUID();
        return json_encode($form);
    }

    private function generateGUID()
    {
        return sprintf('{%04X%04X-%04X-%04X-%04X-%04X%04X%04X}', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    private function getVariable($guid)
    {
        $targets = json_decode($this->ReadPropertyString('Targets'), true);
        foreach ($targets as $target) {
            if ($target['GUID'] == $guid) {
                return $target['VariableID'];
            }
        }
        return 0;
    }

    private function getSceneName($sceneID)
    {
        if ($sceneID != 0) {
            return IPS_GetName($this->GetIDForIdent("Scene$sceneID"));
        } else {
            return $this->Translate('Unknown');
        }
    }

    private function SaveValues($sceneIdent)
    {
        $data = [];

        $targets = json_decode($this->ReadPropertyString('Targets'), true);

        foreach ($targets as $target) {
            $VarID = $target['VariableID'];
            if (!IPS_VariableExists($VarID)) {
                continue;
            }
            $data[$target['GUID']] = GetValue($VarID);
        }

        $sceneData = json_decode($this->ReadAttributeString('SceneData'));

        $i = (int) filter_var($sceneIdent, FILTER_SANITIZE_NUMBER_INT);

        $sceneData[$i - 1] = $data;

        $this->WriteAttributeString('SceneData', json_encode($sceneData));
    }

    private function CallValues($sceneIdent)
    {
        $sceneData = json_decode($this->ReadAttributeString('SceneData'), true);

        $i = (int) filter_var($sceneIdent, FILTER_SANITIZE_NUMBER_INT);

        $data = $sceneData[$i - 1];

        if (count($data) > 0) {
            foreach ($data as $guid => $value) {
                $id = $this->getVariable($guid);
                if (IPS_VariableExists($id)) {
                    $v = IPS_GetVariable($id);
                    if (GetValue($id) == $value) {
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
