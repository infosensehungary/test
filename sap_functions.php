<?php 

function SAPDate2($date) {
	//**10 jegyű dátumból 8 jegyűt csinál (a kötőjeleket üríti)
	return str_replace("-", "", $date);
}

function sendInvoiceToSAP_ByID($parameters) {
	//**A parameters->id azonosítójú Invoiceot ráküldi a sendInvoiceToSAP függvényre
	$id = $parameters->id;
	sendInvoiceToSAP($id);
}

function SAP_FUNCTION_XSTBA($data) {
	//**Az XSTBA mező töltésére szolgáló függvény, mely a fokonyv_t és fokonyv_k mezők alapján 
	//**Amennyiben 443 a mezők értéke, akkor 1-et ad vissza
	$zseb = getObjectElementFullRow($data);
	$fields = ["fokonyv_k", "fokonyv_t"];
	$matches = ["443"];
	$megvan = false;
	foreach ($fields as $field) {
		foreach ($matches as $match) {
			$len = strlen($match);
			if (substr($zseb->$field, 0, $len)==$match) {
				$megvan = true;
			}
		}
	}
	if ($megvan) {
		return "1";
	} else {
		return "";
	}
}

function SAP_FUNCTION_XSTBA_K($data) {
	//**Az XSTBA mező töltésére szolgáló függvény, mely, ha a fokonyv_k mező értéke 443, akkor 1-et ad vissza
	$zseb = getObjectElementFullRow($data);
	$fields = ["fokonyv_k"];
	$matches = ["443"];
	$megvan = false;
	foreach ($fields as $field) {
		foreach ($matches as $match) {
			$len = strlen($match);
			if (substr($zseb->$field, 0, $len)==$match) {
				$megvan = true;
			}
		}
	}
	if ($megvan) {
		return "1";
	} else {
		return "";
	}
}

function SAP_FUNCTION_XSTBA_T($data) {
	//**Az XSTBA mező töltésére szolgáló függvény, mely, ha a fokonyv_t mező értéke 443, akkor 1-et ad vissza
	$zseb = getObjectElementFullRow($data);
	$fields = ["fokonyv_t"];
	$matches = ["443"];
	$megvan = false;
	foreach ($fields as $field) {
		foreach ($matches as $match) {
			$len = strlen($match);
			if (substr($zseb->$field, 0, $len)==$match) {
				$megvan = true;
			}
		}
	}
	if ($megvan) {
		return "1";
	} else {
		return "";
	}
}

function SAP_FUNCTION_KURSR($data) {
	//**Visszatérési értéke: ha a currency mező  HUF, akkor üres, amúgy az arfolyam mező értéke
	$invoice = getDocument($data);
	if ($invoice->currency=="HUF") {
		return "";
	} else {
		return $invoice->arfolyam;
	}
}

function SAP_FUNCTION_BWASL($data) {
	//**Ha a kulcs_t mező értéke 70-nel kezdődik, vagy a kulcs_k 75-tel akkor 100-at ad vissza
	$zseb = getObjectElementFullRow($data);
	$fields = ["kulcs_t"];
	$matches = ["70"];
	$megvan = false;
	foreach ($fields as $field) {
		foreach ($matches as $match) {
			$len = strlen($match);
			if (substr($zseb->$field, 0, $len)==$match) {
				$megvan = true;
			}
		}
	}
	$fields = ["kulcs_k"];
	$matches = ["75"];
	foreach ($fields as $field) {
		foreach ($matches as $match) {
			$len = strlen($match);
			if (substr($zseb->$field, 0, $len)==$match) {
				$megvan = true;
			}
		}
	}
	if ($megvan) {
		return "100";
	} else {
		return "";
	}
}

function SAP_FUNCTION_BZDAT($data) {
	//**Ha a kulcs_t mező értéke 70-nel kezdődik, vagy a kulcs_k 75-tel akkor 100-at ad vissza
	$zseb = getObjectElementFullRow($data);
	$fields = ["kulcs_t"];
	$matches = ["70"];
	$megvan = false;
	foreach ($fields as $field) {
		foreach ($matches as $match) {
			$len = strlen($match);
			if (substr($zseb->$field, 0, $len)==$match) {
				$megvan = true;
			}
		}
	}
	$fields = ["kulcs_k"];
	$matches = ["75"];
	foreach ($fields as $field) {
		foreach ($matches as $match) {
			$len = strlen($match);
			if (substr($zseb->$field, 0, $len)==$match) {
				$megvan = true;
			}
		}
	}
	if ($megvan) {
		return $zseb->bzdat;
	} else {
		return "";
	}
}

function SAP_FUNCTION_MONAT($data) {
	//**Hárombetűs hónapnévből kétszámjegyű hónapszámot ad vissza 
	$months = array('JAN'=>'01', 'FEB'=>'02', 'MAR'=>'03', 'APR'=>'04', 'MAY'=>'05', 'JUN'=>'06', 'JUL'=>'07', 'AUG'=>'08', 'SEP'=>'09', 'OCT'=>'10', 'NOV'=>'11', 'DEC'=>'12');
	return $months[$data];
}

function SAP_FUNCTION_TCODE($data) {
	//**Ha a tcode mező 06, akkor F-47 a visszatérési érték, amúgy FB01 
	if ($data=="06") {
		return "F-47";
	} else {
		return "FB01";
	}
}

function SAP_FUNCTION_DMBTR($data) {
	//**Ha a currency HUF, akkor szla_brutto, ellenkező esetben brutto_huf mező tartalmát adja vissza
	$invoice = getDocument($data);
	if ($invoice->currency=="HUF") {
		return getSAPPrice($invoice->szla_brutto);
	} else {
		return getSAPPrice($invoice->brutto_huf);
	}
}


function SAP_FUNCTION_COSTID($data) {
	//**Átadott costid érték alapján visszaadja a COST id mezőjét.
	$retval = getDBRowData("select id from COST_ID where cost_id='$data'", "id");
	if ($retval=="IT_A" || $retval == "IT_I") {
		$retval = "IT";
	}
	return $retval;
}

function sendInvoiceToSAP($id) {
	//**A sablonok közé feltöltött SAP IF CONTROL azonosítójú sablon excelben megtalálható információk alapján SAP felé küldi a számla adatait (INVOICE, INVOICEATIP, INVVOICEPLUS)
	//**Az Excel leírása: 
	global $dbo, $userdata, $SETTINGS;
	include_once($SETTINGS["DOCROOT"]."/3rdparties/PHPExcel/PHPExcel.php");
	$invoice = getDocument($id);
	$partner = getDocument($invoice->partner_id);
	$inputFileName = getTemplateByID("SAP IF CONTROL");
	//beleolvasunk az excelbe
	//  Read your Excel workbook
	try {
		$inputFileType = PHPExcel_IOFactory::identify($inputFileName);
		$objReader = PHPExcel_IOFactory::createReader($inputFileType);
		$objPHPExcel = $objReader->load($inputFileName);
	} catch(Exception $e) {
		die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
	}
	$ranges[0]=["A7:D34", "F7:I34", "K7:N34"];
	$zsebfield[0]="kontir_2";
	$ranges[1]=["A37:D64", "F37:I64", "K37:N64"];
	$zsebfield[1]="kontir";
	$resultfield[0]="sap_konyv_id";
	$resultfield[1]="sap_konyv_id2";
	$log[0]="KONTÍR";
	$log[1]="KIEG. KONTÍR";
	//plus_kontir mező ha 1, akkor van kieg kontír
	$runs=0;
	if ($invoice->plus_kontir==1 || ($invoice->workflow_id=="INVOICEPLUS")) {
		$runs = 1;
	}
	$bekuldesNeed =0;
	$bekuldve =0;
	$sheetCount = $objPHPExcel->getSheetCount();
	$sheetNames = $objPHPExcel->getSheetNames();
	for ($sheetN=0; $sheetN<$sheetCount; $sheetN++) {
		$sheet = $objPHPExcel->getSheet($sheetN); 
		$kivalasztasFieldRange = $sheet->rangeToArray('A2:B3');//TÖBB IS!!!! KÉT SOR!!!
		$megfelel = true;
		foreach ($kivalasztasFieldRange as $kivalasztasFieldR) {
			if ($kivalasztasFieldR[0]!="") {
				$kivalasztasField = $kivalasztasFieldR[0];
				$objF = explode(".", $kivalasztasFieldR[0]);
				$obj = $objF[0];
				$object = $$obj;
				$objectfield = $objF[1];
				$fieldData = $object->$objectfield;
				if ($fieldData==$kivalasztasFieldR[1] && $kivalasztasFieldR[1]!="") {
					$megfelel = $megfelel & true;
				} else {
					$megfelel = $megfelel & false;
				}
			}
		}
		if ($megfelel) {
			
			for ($run = 0; $run<=$runs; $run++) {
				$resfield = $resultfield[$run];
				if ($invoice->$resfield=="") {
					//KIVÁLASZTÁSI FELTÉTEL
					//FEJLÉC A7:D50
					$data = new stdClass();
					$data->HEADER = new stdClass();
					$headerDef = $sheet->rangeToArray($ranges[$run][0]);
					foreach ($headerDef as $headerRow) {
						if ($headerRow[0]!="") {
							$fieldName = $headerRow[0];
							$fieldData = "";
							$objF = explode(".", $headerRow[1]);
							$obj = $objF[0];
							$object = $$obj;
							$objectfield = $objF[1];
							$fieldData = $object->$objectfield;
							if ($fieldData==null || $fieldData=="") $fieldData = ""; 
							//adattípus = $headerRow[2];
							$fieldData = fieldDefs($fieldData, $headerRow);
							$data->HEADER->$fieldName=$fieldData;
						}
					}	
					if ($run==0) {
						$item1Defs = $sheet->rangeToArray($ranges[$run][1]);
						$zsebek = getAllZsebForDocument($invoice->id, $zsebfield[$run]);
						$zseb = $zsebek[0];
						$dataITEM =  new stdClass();
						foreach ($item1Defs as $item1Def) {
							if ($item1Def[0]!="") {
								$fieldName = $item1Def[0];
								$fieldData = "";
								$objF = explode(".", $item1Def[1]);
								$obj = $objF[0];
								$object = $$obj;
								$objectfield = $objF[1];
								$fieldData = $object->$objectfield;
								if ($fieldData==null || $fieldData=="") $fieldData = ""; 
								//adattípus = $headerRow[2];
								$fieldData = fieldDefs($fieldData, $item1Def);
								$dataITEM->$fieldName=$fieldData;
							}
						}	
						$data->ITEMS[]=$dataITEM;
						$zsebek = getAllZsebForDocument($invoice->id, $zsebfield[$run]);
						$item2Defs = $sheet->rangeToArray($ranges[$run][2]);
						foreach ($zsebek as $zseb) {
							$dataITEM =  new stdClass();
							foreach ($item2Defs as $item2Def) {
								if ($item2Def[0]!="") {
									$fieldName = $item2Def[0];
									$fieldData = "";
									$objF = explode(".", $item2Def[1]);
									$obj = $objF[0];
									$object = $$obj;
									$objectfield = $objF[1];
									$fieldData = $object->$objectfield;
									if ($fieldData==null || $fieldData=="") $fieldData = ""; 
									//adattípus = $headerRow[2];
									$fieldData = fieldDefs($fieldData, $item2Def);
									$dataITEM->$fieldName=$fieldData;
								}
							}
							$data->ITEMS[]=$dataITEM;
						}					
					}
					if ($run==1) { //KIEGÉSZÍTŐ KONTÍR
						$dataITEM =  new stdClass();
						$zsebek = getAllZsebForDocument($invoice->id, $zsebfield[$run]);
						foreach ($zsebek as $zseb) {
							for ($kontirPart=1; $kontirPart<=2; $kontirPart++) {
								$item2Defs = $sheet->rangeToArray($ranges[$run][$kontirPart]);
								$dataITEM =  new stdClass();
								foreach ($item2Defs as $item2Def) {
									if ($item2Def[0]!="") {
										$fieldName = $item2Def[0];
										$fieldData = "";
										$objF = explode(".", $item2Def[1]);
										$obj = $objF[0];
										$object = $$obj;
										$objectfield = $objF[1];
										$fieldData = $object->$objectfield;
										if ($fieldData==null || $fieldData=="") $fieldData = ""; 
										//adattípus = $headerRow[2];
										$fieldData = fieldDefs($fieldData, $item2Def);
										$dataITEM->$fieldName=$fieldData;
									}
								}
								$data->ITEMS[]=$dataITEM;							
							}
					
						}		
					}
					$arr = (array)$data->HEADER;
					if ($arr && count($data->ITEMS)>0) {
						$bekuldesNeed++;
						$result = QuerySAPBPKonyveles($data, "post");
						$parameters2 = new stdClass();
						$parameters2->id=$id;
						$parameters2->data="Küldött JSON (".$log[$run]."): ".json_encode($data);
						$parameters2->itemtype=$invoice->workflow_id;
						addAnonymousNoteChat($parameters2);
						//	print_r($result);
						//sap_konyv_id írása, ha van SUCCESS érték
						if ($result->RESPONSE->SUCCESS!="") {
							if ($result->DOCUMENT->BELNR!="") {
								$bekuldve++;
								saveDocumentField($id, $resultfield[$run], $result->DOCUMENT->BELNR);
							}
						} else {
							if ($result->RESPONSE->MSGTX!="") {
								//note, ha van MSGTX érték
								$parameters2 = new stdClass();
								$parameters2->id=$id;
								$parameters2->data="SAP válasza (".$log[$run]."): ".$result->RESPONSE->MSGTX;
								$parameters2->itemtype=$invoice->workflow_id;
								addAnonymousNoteChat($parameters2);
							}
						}					
					}					
				} else {
					//itt azt kell megnézni, hogy volt már beküldve sikeresen 
					$bekuldesNeed++;
					$bekuldve++;
				}
			}
			break;
		}	
	}
	errorLog("SAPBA", $bekuldesNeed." ".$bekuldve);
	if ($bekuldesNeed<>$bekuldve) {
		exit;			
	}
}

function getSAPPrice($fieldData) {
	//**Price típusú mezők esetén visszaadja a fieldData paraméterben kapott érték SAP-kompatibilis értékét (két tizedesjegyre végződő, ponttal elválasztott, nem negatív szám)
	$fieldData = str_replace("-", "", $fieldData);
	$val = explode(".", $fieldData);
	if (count($val)==1) {
		$fieldData = $fieldData.".00";
	}
	return $fieldData;
}

function fieldDefs($fieldData, $fieldDef) {
	//**fieldDef változóban kapott (price, fix, date, dataset, function, text és substr) típusú mezőmegfeleltetést készít fieldData változó alapján
	//**-price: getSAPPrice függvénynek adja át a kapott fieldData értéket
	//**-fix: fieldDef második eleme értékét változtatás nélkül visszaadja
	//**-date: SAPDate2 függvénynek adja át a kapott fieldData értéket
	//**-dataset: dataset értéket ad vissza fieldData érték alapján
	//**-function: SAP_FUNCTION_XXX függvénynek adja át fieldData értéket
	//**-text: SAPDate2 függvénynek adja át a kapott fieldData értéket
	//**-substr: fieldDef 3. eleme számú hosszan adja vissza egy string részét
	if ($fieldDef[2]=="fix") $fieldData=$fieldDef[1];
	if (($fieldData==null || $fieldData=="")) {
		return "";
	} else {
		switch ($fieldDef[2]) {
			case "price":
				$fieldData = getSAPPrice($fieldData);
				break;
			case "fix":
				$fieldData = $fieldDef[1];
				break;
			case "date":
				$fieldData = SAPDate2($fieldData);
				break;
			case "dataset":
				$fieldData = getDatasetItem($fieldDef[3], $fieldData);
				break;
			case "function":
				$functionname = "SAP_FUNCTION_".$fieldDef[3];
				$fieldData = $functionname($fieldData);
				break;
			case "text":
				$fieldData = $fieldData;
				break;			
			case "substr":
				$fieldData = mb_substr($fieldData, 0, $fieldDef[3]);
				break;			
		}
		return $fieldData;
	}
}

function addAnonymousNoteChat($parameters) {
	//**Névtelen megjegyzést fűz a parameters változóban definiált dokumentumhoz. SAP válaszokat tárolunk így el egy beküldött számlához.
	global $userdata, $WORKFLOWS, $SETTINGS;
	$id = $parameters->id;
	$data = $parameters->data;
	$itemtype=$parameters->itemtype;
	if ($data!="") {
		runSQL("insert into NOTES (document_id, item_type, created, userid, note) values ('$id', '$itemtype', now(), 0, '$data')");
	}	
}

function QuerySAPBPKonyveles($queryData, $link) {
	//**A queryData változóban megkapott adatstruktúrát az SAP interfészen beküldi, majd a választ az API log táblában rögzíti.
	global $SETTINGS;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $SETTINGS["SAP_LINK"]."/$link");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERPWD, $SETTINGS["SAP_USER"].":".$SETTINGS["SAP_PASSWORD"]);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($queryData, JSON_UNESCAPED_UNICODE));
	if ($SETTINGS["SAP_CERT_LOCATION"]!="") {
		curl_setopt($ch, CURLOPT_SSLCERT, $SETTINGS["SAP_CERT_LOCATION"]);
		curl_setopt($ch, CURLOPT_SSLCERTTYPE, $SETTINGS["SAP_CERT_TYPE"]);
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:application/json"]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	if (curl_errno($ch)) {
		logAPI("SAPBUSINESS", $_SERVER["REMOTE_ADDR"], (json_encode($queryData, JSON_UNESCAPED_UNICODE)), "ERROR CONNECTING API: ".curl_error($ch));
		return false;
	} else {
		curl_close($ch);
		$result2 = json_decode($result);
		logAPI("SAPBUSINESS", $_SERVER["REMOTE_ADDR"], ($result), (json_encode($queryData, JSON_UNESCAPED_UNICODE)));
		return $result2;
	}
}

function modifyPartnerBySAP($id, $data) {
	$saptype[1]=2;
	$saptype[2]=1;
	saveDocumentField($id, "name", $data->PARTNER->NAME);
	saveDocumentField($id, "address", $data->PARTNER->ADDRESS);
	saveDocumentField($id, "adoszam", $data->PARTNER->ASZ);
	saveDocumentField($id, "cegjegyzekszam", $data->PARTNER->CJSZ);
	saveDocumentField($id, "orszagkod", $data->PARTNER->COUNTRY);
	saveDocumentField($id, "type", $saptype[$data->PARTNER->TYPE]);

	saveDocumentField($id, "name1", $data->VENDOR->NAME);
	saveDocumentField($id, "LIFNR", $data->VENDOR->LIFNR);
	saveDocumentField($id, "stcd1", $data->VENDOR->STCD1);
	saveDocumentField($id, "akont", $data->VENDOR->AKONT);

	$address = explode(",", $data->VENDOR->ADDRESS);
	saveDocumentField($id, "pstlz", $address[0]);
	saveDocumentField($id, "ort01", $address[1]);
	saveDocumentField($id, "stras", $address[2]);

	$bankok = getAllZsebForDocument($id, "bankszamlaszam");
	foreach ($bankok as $bank) {
		deleteObjectElement_Simple($bank->id);
	}
	runSQL2("delete from OBJECT_ELEMENTS_SEQUENCE where document_id=? and fieldname='bankszamlaszam'", [$id]);
	foreach ($data->ACCOUNTS as $bank) {
		$banksz = $bank->BANKL."-".$bank->BANKN;
		$rownum=getNextObjectElementRowNum($id, "bankszamlaszam");
		$itemid = runInsertSQL2("insert into OBJECT_ELEMENTS (document_id, rownumber, fieldname) values (?, ?, ?)", [$id, $rownum, "bankszamlaszam"]);
		runSQL2("insert into OBJECT_ELEMENTS_ITEMS (object_element_id, fieldname, fieldvalue) values (?, ?, ?)", [$itemid, "bankszamlaszam", $banksz]);
	}
}

function QuerySAPBP($data) {
	global $SETTINGS;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $SETTINGS["SAP_LINK"]."/bp");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERPWD, $SETTINGS["SAP_USER"].":".$SETTINGS["SAP_PASSWORD"]);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	if ($SETTINGS["SAP_CERT_LOCATION"]!="") {
		curl_setopt($ch, CURLOPT_SSLCERT, $SETTINGS["SAP_CERT_LOCATION"]);
		curl_setopt($ch, CURLOPT_SSLCERTTYPE, $SETTINGS["SAP_CERT_TYPE"]);
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:application/json"]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	if (curl_errno($ch)) {
		logAPI("SAPBUSINESS", $_SERVER["REMOTE_ADDR"], (json_encode($data)), "ERROR CONNECTING API: ".curl_error($ch));
		return false;
	} else {
		curl_close($ch);
		$result = json_decode($result);
		logAPI("SAPBUSINESS", $_SERVER["REMOTE_ADDR"], (json_encode($data)), serialize($result));
		return $result;
	}
}