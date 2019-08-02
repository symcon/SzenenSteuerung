<?php
class SzenenSteuerung extends IPSModule
{

	public function Create()
	{
		//Never delete this line!
		parent::Create();

		//Properties
		$this->RegisterPropertyInteger("SceneCount", 5);
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

		$this->CreateCategoryByIdent($this->InstanceID, "Targets", "Targets");

		for ($i = 1; $i <= $this->ReadPropertyInteger("SceneCount"); $i++) {
			$variableID = $this->RegisterVariableInteger("Scene" . $i, "Scene" . $i, "SZS.SceneControl");
			$this->EnableAction("Scene" . $i);
			SetValue($variableID, 2);
		}

		for ($k = 1; $k <= $this->ReadPropertyInteger("SceneCount"); $k++) {
			$SceneDataID = @$this->GetIDForIdent("Scene" . $i . "Data");
			if ($SceneDataID && function_exists("wddx_deserialize")) {

				$data = wddx_deserialize(GetValue($SceneDataID));
				if ($data !== NULL) {
					SetValue($SceneDataID, json_encode($data));
				}
			}
		}
		$SceneData = json_decode($this->ReadAttributeString("SceneData"));

		if (!is_array($SceneData)) {
			$SceneData = [];
		}

		for ($i = 1; $i <= $this->ReadPropertyInteger("SceneCount"); $i++) {
			$ObjectID = @$this->GetIDForIdent("Scene" . $i . "Data");
			if (!array_key_exists($i - 1, $SceneData)) {
				if ($ObjectID) {
					$decodedSceneData = json_decode(GetValue($ObjectID));
					if ($decodedSceneData) {
						$SceneData[$i - 1] = $decodedSceneData;
					} else {
						$SceneData[$i - 1] = new stdClass;
					}
				} else {
					$SceneData[$i - 1] = new stdClass;
				}
			}

			if ($ObjectID) {
				$this->UnregisterVariable("Scene" . $i . "Data");
			}

			if (!@$this->GetIDForIdent("Scene" . $i)) {
				$this->RegisterStringVariable("Scene" . $i);
			}
		}

		$this->WriteAttributeString("SceneData", json_encode($SceneData));

		$SceneCount = $this->ReadPropertyInteger("SceneCount") + 1;


		for ($i = $SceneCount;; $i++) {
			if (@$this->GetIDForIdent("Scene" . $i)) {
				$this->UnregisterVariable("Scene" . $i);

				if (@$this->GetIDForIdent("Scene" . $i . "Data")) {
					$this->UnregisterVariable("Scene" . $i . "Data");
				}
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

		$targetIDs = IPS_GetObjectIDByIdent("Targets", $this->InstanceID);
		$data = array();

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

		$SceneData = json_decode($this->ReadAttributeString("SceneData"));

		$i = intval(substr($SceneIdent, -1));

		$data = $SceneData[$i - 1];

		foreach ($data as $key => $value) {
			if (!IPS_VariableExists($key)) {
				continue;
			}

			$v = IPS_GetVariable($key);

			if ($v['VariableCustomAction'] > 0) {
				$actionID = $v['VariableCustomAction'];
			} else {
				$actionID = $v['VariableAction'];
			}
			//Skip this device if we do not have a proper id
			if ($actionID < 10000) {
				continue;
			}

			RequestAction($key, $value);
		}
	}

	private function CreateCategoryByIdent($id, $ident, $name)
	{
		$cid = @IPS_GetObjectIDByIdent($ident, $id);
		if ($cid === false) {
			$cid = IPS_CreateCategory();
			IPS_SetParent($cid, $id);
			IPS_SetName($cid, $name);
			IPS_SetIdent($cid, $ident);
		}
		return $cid;
	}
}
