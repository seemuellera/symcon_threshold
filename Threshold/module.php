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
			
			if (! @$this->GetIDForIdent("NumericalThreshold")) {
				
				$this->RegisterVariableInteger("NumericalThreshold","Numerical Threshold");
			}
		}
		else {
			
			if (! @$this->GetIDForIdent("NumericalThreshold")) {
				
				$this->UnregisterVariableInteger("NumericalThreshold");
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
		
		switch ($this->ReadPropertyString("CompareMode") ) {
			
			case "LargerThan":
				$this->CheckLargerThan();
				break;
			case "LessThan":
				$this->CheckLessThan();
				break;
			default:
				$this->LogMessage("Compare mode is not implemented","ERROR");
		}
	}
	
	protected function CheckLargerThan() {
		
		if (GetValue($this->ReadPropertyInteger("SourceVariable")) > $this->ReadPropertyFloat("NumericalThreshold") ) {
			
			$this->UpdateAlertState(true);
		}
		else {
			
			$this->UpdateAlertState(false);
		}
	}
	
	protected function CheckLessThan() {
		
		if (GetValue($this->ReadPropertyInteger("SourceVariable")) < $this->ReadPropertyFloat("NumericalThreshold") ) {
			
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
