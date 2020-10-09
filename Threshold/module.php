<?php

// Klassendefinition
class Threshold extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","Threshold");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("SourceVariable",0);
		$this->RegisterPropertyString("CompareMode","LargerThan");
		$this->RegisterPropertyFloat("NumericalThreshold",0);
		$this->RegisterPropertyBoolean("ExportNumericalThreshold",false);
		$this->RegisterPropertyString("CompareText","");
		$this->RegisterPropertyBoolean("CompareAverageValues",false);
		$this->RegisterPropertyInteger("ArchiveId",0);
		$this->RegisterPropertyInteger("AverageMinutes",5);
		$this->RegisterPropertyBoolean("ExportAverageValues",false);
		$this->RegisterPropertyBoolean("CheckSourceAge",false);
		$this->RegisterPropertyInteger("MaxSourceAge",60);
		$this->RegisterPropertyBoolean("ResultIfOutdated",false);
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableBoolean("Result","Result","~Alert");
		
		//Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'Threshold_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		$this->RegisterMessage($this->ReadPropertyInteger("SourceVariable"), VM_UPDATE);
		
		if ($this->ReadPropertyBoolean("ExportNumericalThreshold")) {
			
			if (! @$this->GetIDForIdent("NumericalThresholdVariable")) {
				
				$this->RegisterVariableFloat("NumericalThresholdVariable","Numerical Threshold");
			}
			
			SetValue($this->GetIDForIdent("NumericalThresholdVariable"),$this->ReadPropertyFloat("NumericalThreshold"));
		}
		else {
			
			if (! @$this->GetIDForIdent("NumericalThresholdVariable")) {
				
				$this->UnregisterVariable("NumericalThresholdVariable");
			}
		}
		
		if ($this->ReadPropertyBoolean("ExportAverageValues")) {
			
			if (! @$this->GetIDForIdent("AverageValue")) {
				
				$this->RegisterVariableFloat("AverageValue","Average Value");
			}
		}
		else {
			
			if (! @$this->GetIDForIdent("AverageValue")) {
				
				$this->UnregisterVariable("AverageValue");
			}
		}
			
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}


	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "SourceVariable", "caption" => "Source Variable");
		
		$form['elements'][] = Array(
								"type" => "Select", 
								"name" => "CompareMode", 
								"caption" => "Select Comparison Mode",
								"options" => Array(
									Array(
										"caption" => "LargerThan (numerical compare) - Result is Alarm if the variable value is larger than the threshold",
										"value" => "LargerThan"
									),
									Array(
										"caption" => "LessThan (numerical compare) - Result is Alarm if the variable value is less than the threshold",
										"value" => "LessThan"
									)
								)
							);
		
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "NumericalThreshold", "caption" => "Numerical Threshold (for numerical compare modes)", "digits" => 3);
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ExportNumericalThreshold", "caption" => "Export Numerical Threshold to a variable");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "CompareText", "caption" => "Compare Text (for text compare modes)");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "CompareAverageValues", "caption" => "Compare Average value (for numerical compare modes)");
		$form['elements'][] = Array("type" => "SelectInstance", "name" => "ArchiveId", "caption" => "Id of the corresponding archive");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "AverageMinutes", "caption" => "Average Timeframe in Minutes");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ExportAverageValues", "caption" => "Export average value to a variable");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "CheckSourceAge", "caption" => "Check the age of source variable");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "MaxSourceAge", "caption" => "Maximum Source Age in seconds. Make sure that it is aligned with the refresh interval.");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "ResultIfOutdated", "caption" => "Value of the result if the variable is outdated (active => Alert)");	
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'THRESHOLD_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Compare Now", "onClick" => 'THRESHOLD_Check($id);');
		// Return the completed form
		return json_encode($form);

	}
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		
		IPS_LogMessage($this->ReadPropertyString('Sender') . " - " . $this->InstanceID, $messageComplete);
	}

	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		$this->Check();
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				// Turn alarm off when checking is deactivated.
				if (! $Value) {
					
					SetValue($this->GetIDForIdent("Result"), false);
				}
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
	
	public function Check() {
		
		if (! GetValue($this->GetIDForIdent("Status")) ) {
			
			$this->LogMessage("Threshold will not be checked because checking is deactivated","DEBUG");
		}
		
		if ($this->ReadPropertyBoolean("CheckSourceAge")) {
			
			$this->LogMessage("Source age check is active","DEBUG");
			
			$sourceDetails = IPS_GetVariable($this->ReadPropertyInteger("SourceVariable"));
			$tsLastUpdate = $sourceDetails['VariableUpdated'];
			
			if ($tsLastUpdate < (time() - $this->ReadPropertyInteger("MaxSourceAge") ) ) {
				
				$this->LogMessage("Source variable is outdated","DEBUG");
				
				if ($this->ReadPropertyBoolean("ResultIfOutdated")) {
					
					$this->UpdateAlertState(true);
					return;
				}
				else {
					
					$this->UpdateAlertState(false);
					return;
				}
			}
		}
		
		if ($this->ReadPropertyBoolean("CompareAverageValues")) {
			
			$startTime = time() - $this->ReadPropertyInteger("AverageMinutes") * 60;
			$results = AC_GetAggregatedValues($this->ReadPropertyInteger("ArchiveId"), $this->ReadPropertyInteger("SourceVariable"), 6, $startTime, time(), 0);
			
			$averageSum = 0;
			foreach ($results as $result) {
				
				$averageSum += $result['Avg'];
			}
			
			$inputValue = $averageSum / count($results);
			
			if ($this->ReadPropertyBoolean("ExportAverageValues")) {
				
				SetValue($this->GetIDForIdent("AverageValue"),$inputValue);
			}
		}
		else {
			
			$inputValue = GetValue($this->ReadPropertyInteger("SourceVariable"));
		}
		
		switch ($this->ReadPropertyString("CompareMode") ) {
			
			case "LargerThan":
				$this->CheckLargerThan($inputValue);
				break;
			case "LessThan":
				$this->CheckLessThan($inputValue);
				break;
			default:
				$this->LogMessage("Compare mode is not implemented","ERROR");
		}
	}
	
	protected function CheckLargerThan($inputValue) {
		
		if ($inputValue > $this->ReadPropertyFloat("NumericalThreshold") ) {
			
			$this->UpdateAlertState(true);
		}
		else {
			
			$this->UpdateAlertState(false);
		}
	}
	
	protected function CheckLessThan($inputValue) {
		
		if ($inputValue < $this->ReadPropertyFloat("NumericalThreshold") ) {
			
			$this->UpdateAlertState(true);
		}
		else {
			
			$this->UpdateAlertState(false);
		}
	}
	
	protected function UpdateAlertState($alertState) {
		
		if ( (GetValue($this->GetIDForIdent("Result"))) && (! $alertState) ) {
			
			$this->LogMessage("Changing back from Alert state to non-Alert state","DEBUG");
			SetValue($this->GetIDForIdent("Result"), $alertState);
		}
		
		if ( (! GetValue($this->GetIDForIdent("Result"))) && ($alertState) ) {
			
			$this->LogMessage("Changing from non-Alert state to Alert state","DEBUG");
			SetValue($this->GetIDForIdent("Result"), $alertState);
		}
	}
}
