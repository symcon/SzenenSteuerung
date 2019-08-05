<?php
class SzenenSteuerung extends IPSModule
{

	public function Create()
	{
		//Never delete this line!
		parent::Create();

		//Properties
		$this->RegisterPropertyInteger("SceneCount", 5);
		//Attributes
		$this->RegisterAttributeString("SceneData", "[]");
		$this->RegisterPropertyString("VariablesToSwitch", "[]");

		if (!IPS_VariableProfileExists("SZS.SceneControl")) {
			IPS_CreateVariableProfile("SZS.SceneControl", 1);
			IPS_SetVariableProfileValues("SZS.SceneControl", 1, 2, 0);
			//IPS_SetVariableProfileIcon("SZS.SceneControl", "");
			IPS_SetVariableProfileAssociation("SZS.SceneControl", 1, "Speichern", "", -1);
			IPS_SetVariableProfileAssociation("SZS.SceneControl", 2, "Ausführen", "", -1);
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
		if ($this->ReadPropertyString("VariablesToSwitch") == "[]") {
			$TargetID = @$this->GetIDForIdent("Targets");

			if ($TargetID) {

				$Variables = [];
				foreach (IPS_GetChildrenIDs($TargetID) as $ChildrenID) {
					$targetID = IPS_GetLink($ChildrenID)["TargetID"];
					$line = [
						"VariableID" => $targetID
					];
					array_push($Variables, $line);
					IPS_DeleteLink($ChildrenID);
				}

				IPS_DeleteCategory($TargetID);
				IPS_SetProperty($this->InstanceID, "VariablesToSwitch", json_encode($Variables));
				IPS_ApplyChanges($this->InstanceID);
				return;
			}
		}


		$SceneCount = $this->ReadPropertyInteger("SceneCount");

		//Create Scene variables
		for ($i = 1; $i <= $SceneCount; $i++) {
			$variableID = $this->RegisterVariableInteger("Scene" . $i, "Scene" . $i, "SZS.SceneControl");
			$this->EnableAction("Scene" . $i);
			SetValue($variableID, 2);
		}

		//Import from WDDX data into new JSON data
		for ($i = 1; $i <= $SceneCount; $i++) {
			$SceneDataID = @$this->GetIDForIdent("Scene" . $i . "Data");
			if ($SceneDataID && function_exists("wddx_deserialize")) {

				$data = wddx_deserialize(GetValue($SceneDataID));
				if ($data !== NULL) {
					SetValue($SceneDataID, json_encode($data));
				}
			}
		}
				
		$SceneData = json_decode($this->ReadAttributeString("SceneData"));
		
		//If older versions contain errors regarding SceneData SceneControl would become unusable otherwise, even in fixed versions
		if (!is_array($SceneData)) {
			$SceneData = [];
		}

		//Preparing SceneData for later use
		$SceneCount = $this->ReadPropertyInteger("SceneCount");

		for ($i = 1; $i <= $SceneCount; $i++) {
			if (!array_key_exists($i - 1, $SceneData)) {
				$SceneData[$i - 1] = new stdClass;
			}
			
		}

		//Getting data from legacy SceneData variables to put them in new SceneData attribute 
		for ($i = 1; $i <= $SceneCount; $i++) {
			$ObjectID = @$this->GetIDForIdent("Scene" . $i . "Data");
			if (!array_key_exists($i - 1, $SceneData)) {
				if ($ObjectID) {
					$decodedSceneData = json_decode(GetValue($ObjectID));
					if ($decodedSceneData) {
						$SceneData[$i - 1] = $decodedSceneData;
					}
					$this->UnregisterVariable("Scene" . $i . "Data");
				}
			}
		}

		//Deleting SceneData variables used in legacy
		for ($i = $SceneCount + 1;; $i++) {
			if (@$this->GetIDForIdent("Scene" . $i . "Data")) {
				$this->UnregisterVariable("Scene" . $i . "Data");
			} else {
				break;
			}
		}

		//Deleting surplus data in SceneData
		$SceneData = array_slice($SceneData, 0, $SceneCount);
		$this->WriteAttributeString("SceneData", json_encode($SceneData));

		//Deleting surplus variables
		for ($i = $SceneCount + 1;; $i++) {
			if (@$this->GetIDForIdent("Scene" . $i)) {
				$this->UnregisterVariable("Scene" . $i);
				
			} else {
				break;
			}
		}
	}

	public function RequestAction($Ident, $Value)
	{

		switch ($Value) {
			case "1":
				$this->SaveValues($Ident);
				break;
			case "2":
				$this->CallValues($Ident);
				break;
			default:
				throw new Exception("Invalid action");
		}
	}

	public function CallScene(int $SceneNumber)
	{

		$this->CallValues("Scene" . $SceneNumber);
	}

	public function SaveScene(int $SceneNumber)
	{

		$this->SaveValues("Scene" . $SceneNumber);
	}

	private function SaveValues($SceneIdent)
	{

		$data = [];

		$VariablesToSwitch = json_decode($this->ReadPropertyString("VariablesToSwitch"), true);

		foreach ($VariablesToSwitch as $line) {
			$VarID = $line["VariableID"];
			if (!IPS_VariableExists($VarID)) {
				continue;
			}
			$data[$VarID] = GetValue($VarID);
		}

		$sceneData = json_decode($this->ReadAttributeString("SceneData"));

		$i = intval(substr($SceneIdent, -1));

		$sceneData[$i - 1] = $data;

		$this->WriteAttributeString("SceneData", json_encode($sceneData));
	}

	private function CallValues($SceneIdent)
	{

		$SceneData = json_decode($this->ReadAttributeString("SceneData"), true);

		$i = intval(substr($SceneIdent, -1));

		$data = $SceneData[$i - 1];

		if (count($data) > 0) {
			foreach ($data as $id => $value) {
				if (IPS_VariableExists($id)) {

					$v = IPS_GetVariable($id);

					if ($v['VariableCustomAction'] > 0) {
						$actionID = $v['VariableCustomAction'];
					} else {
						$actionID = $v['VariableAction'];
					}
					//Skip this device if we do not have a proper id
					if ($actionID < 10000)
						continue;

					RequestAction($id, $value);
				}
			}
		}
	}

}
