<?php

declare(strict_types=1);
class SzenenSteuerung extends IPSModule
{
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

        //Transfer data from Target Category(legacy) to recent List
        if ($this->ReadPropertyString('Targets') == '[]') {
            $targetID = @$this->GetIDForIdent('Targets');

            if ($targetID) {
                $variables = [];
                foreach (IPS_GetChildrenIDs($targetID) as $childID) {
                    $targetID = IPS_GetLink($childID)['TargetID'];
                    $line = [
                        'VariableID' => $targetID
                    ];
                    array_push($variables, $line);
                    IPS_DeleteLink($childID);
                }

                IPS_DeleteCategory($targetID);
                IPS_SetProperty($this->InstanceID, 'Targets', json_encode($variables));
                IPS_ApplyChanges($this->InstanceID);
                return;
            }
        }

        $sceneCount = $this->ReadPropertyInteger('SceneCount');

        //Create Scene variables
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

        //Getting data from legacy Scene Data to put them in SceneData attribute (including wddx, JSON)
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

        //Add references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        $targets = json_decode($this->ReadPropertyString('Targets'));
        foreach ($targets as $target) {
            $this->RegisterReference($target->VariableID);
        }

        //Unregister all messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }

        //Register messages if neccessary
        foreach ($targets as $target) {
            $this->RegisterMessage($target->VariableID, VM_UPDATE);
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
            foreach ($scenes[$i] as $id => $value) {
                $sceneID = $i;
                if (GetValue($id) != $value) {
                    $sceneID = -1;
                    break;
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
            $data[$VarID] = GetValue($VarID);
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
            foreach ($data as $id => $value) {
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
