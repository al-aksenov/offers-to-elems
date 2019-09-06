<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("RUN");

CModule::IncludeModule("iblock");
CModule::IncludeModule("file");
ini_set("max_execution_time", "600");

// ������ � ������� �����
$dataAr = Array (
	"IBLOCK_ID" 	=> 3,    			//ID ��������� � �������� 
	"CATALOG_TYPE" 	=> 3, 				//��� ������� ���� "����� � ��������� �������������"
	"IBLOCK_ID_SKU" => 10,    			//ID ��������� � ��������� �������������
	"PROPERTY_STR"	=> ["ARTNUMBER"],	//������ ���� ��������� �������, �����������
										//�� �������� �����������
	"PROPERTY_LIST"	=> ["COLOR", 		//������ ���� ������� ���� "������", �����������
						"SIZE"],		//�� �������� �����������								
);

$arFilter = Array (
	"IBLOCK_ID" => $dataAr["IBLOCK_ID"],
	"CATALOG_TYPE" => $dataAr["CATALOG_TYPE"],
); 

$res = CIBlockElement::GetList(Array("ID"=>"ASC"), $arFilter);

while($ob = $res->GetNextElement()){ 

	$arFieldsCurrent = $ob->GetFields();
	$arPropsCurrent = $ob->GetProperties();
	$currentID = $arFieldsCurrent["ID"];
	
	//������ ����� ��� ������ ��������
	$arFieldsNew = Array(
		"IBLOCK_SECTION_ID" => $arFieldsCurrent["IBLOCK_SECTION_ID"],
		"IBLOCK_ID"      	=> $dataAr["IBLOCK_ID"],
		"ACTIVE"         	=> $arFieldsCurrent["ACTIVE"],
		"SORT"           	=> $arFieldsCurrent["SORT"],
		"PREVIEW_TEXT"   	=> $arFieldsCurrent["~PREVIEW_TEXT"],
		"DETAIL_TEXT"    	=> $arFieldsCurrent["DETAIL_TEXT"],
		"DETAIL_TEXT_TYPE" 	=> "html",
    );
	
	//������ ������� ��� ������ ��������
	$arPropsNew = Array();
	foreach ($arPropsCurrent as $key => $value) {
		// ���� �������� �����������, ��������� ������ ��� ������ �������� � ������ $arPropsNew 
		if ($value['VALUE']) {
			// $arPropsNew[$key] ��� �������� ���� "������" ..
			if ($value['PROPERTY_TYPE'] == 'L'){
				// ... �� �������������� ������
				if ($value['MULTIPLE'] == 'N') {
					$arPropsNew[$key] = Array("VALUE" => $value["VALUE_ENUM_ID"]);
				}
				// ... �������������� ������
				if ($value['MULTIPLE'] == 'Y') {
					$arPropsNew[$key] = $value["VALUE_ENUM_ID"];
				}
			}
			// $arPropsNew[$key] ��� �������� � �������� �����������
			elseif ($key == 'MORE_PHOTO') {
				continue; 	// ��� �������� �� ��������� ����� ����� ������ 
							// � ������������� ��� ������� ������ ������
							// ����� �������� ������ ��� ������� ������ ������
							// � ����� �� $offers
			}
			// $arPropsNew[$key] ��� ��������� �������
			else {
				$arPropsNew[$key] = $value['VALUE'];
			}
		}
	}
		
	//�������� ������ ID �������� ����������� � �������� �������� (������)
	$offers = CCatalogSKU::getOffersList([$currentID], $dataAr["IBLOCK_ID"])[$currentID];
	foreach ($offers as $keyOffers => $valueOffers) {
		
		// �������� ����� �� ������� ����������� �������� �������� (������)
		$arPropsNew = Array();
		foreach ($arPropsCurrent as $key => $value) {
			if ($value['VALUE'] and $key == 'MORE_PHOTO') {
				$arPropsNew[$key] = Array();
				foreach($value['VALUE'] as $numF => $currentFotoID) {
					// ��������� ������ $arPropsNew['MORE_PHOTO'] ����������, �������������� �� ������
					$arPropsNew[$key]["n{$numF}"] =  Array(
						"VALUE" => CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . CFile::GetFileArray($currentFotoID)["SRC"])
					);
				}
			}
		}
		
		// �������� ���, ���������� � �������� �������� ��������� �����������
		$resOffer = CIBlockElement::GetList(Array(), Array (
			"IBLOCK_ID" => $dataAr["IBLOCK_ID_SKU"],
			"ID" => $keyOffers,
			) 
		);
		if($obOffer = $resOffer->GetNextElement()){
			
			$arFieldsOffer = $obOffer->GetFields();
			$newName = $arFieldsOffer["~NAME"];
			$new��tivity = $arFieldsOffer['ACTIVE'];

			foreach ($dataAr["PROPERTY_STR"] as $prop) {
				$art = $obOffer->GetProperty($prop)["VALUE"];
				if ($art) {
					$arPropsNew[$prop] = $art;
				}
			}	
			
			foreach ($dataAr["PROPERTY_LIST"] as $prop) {
				$propValue = $obOffer->GetProperty($prop)["VALUE"];
				if ($propValue) {
					$enumList = CIBlockProperty::GetPropertyEnum(
						"COLOR", 
						Array(), 
						Array(
							"VALUE" 	=> $propValue,
							"IBLOCK_ID"	=> $dataAr["IBLOCK_ID"],
						)
					);
					if($ar_enum_list = $enumList->GetNext()) {
						$propIDNew = $ar_enum_list['ID'];
						$arPropsNew[$prop] = Array($propIDNew);
					}
				}
			}
			$arFieldsNew["NAME"] = $newName;
			$arFieldsNew["CODE"] = Cutil::translit($newName,"ru",array());
			$arFieldsNew["ACTIVE"] = $new��tivity;			
		}
		$arFieldsNew["PROPERTY_VALUES"] = $arPropsNew;
				
		//������� ����� �������
		$el = new CIBlockElement;
		if($newID = $el->Add($arFieldsNew)) {
			echo 'New ID: ' . $newID . '<br>';
		}
		else {
			$arFieldsNew["CODE"] = $arFieldsNew["CODE"] . "_" . $keyOffers;
			if($newID = $el->Add($arFieldsNew)) {
				echo 'New ID: ' . $newID . '<br>';
			}
			else {
				echo 'Error: ' . $el->LAST_ERROR  . '<br>';
				break;	
			}
		}
		
		// ��������� � ���������� �������� ��������� �������� � �������� ������
		if($arFieldsCurrent["DETAIL_PICTURE"]){
			$arNewDetPicFields['DETAIL_PICTURE'] = CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . CFile::GetFileArray($arFieldsCurrent["DETAIL_PICTURE"])["SRC"]);
			$el->Update($newID, $arNewDetPicFields);
		}
		if($arFieldsCurrent["PREVIEW_PICTURE"]){
			$arNewPrePicFields['PREVIEW_PICTURE'] = CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . CFile::GetFileArray($arFieldsCurrent["PREVIEW_PICTURE"])["SRC"]);
			$el->Update($newID, $arNewPrePicFields);
		}
					
		//����������� ����� ������� �� ���� ��������, � ������� �������� ������� ������� 
		$binded = CIBlockElement::GetElementGroups($currentID);
		$ar_new_groups = Array();
		while($arBinded = $binded->Fetch()) {
			$ar_new_groups[] = $arBinded["ID"].'<br>';
		}
		CIBlockElement::SetElementSection($newID, $ar_new_groups);
		\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(3, $newID);
		
		//������������� ���������� � ����
		$arFieldsCat = array(
			"ID" 		=> $newID,
			"QUANTITY" 	=> CCatalogProduct::GetByID($keyOffers)["QUANTITY"],
			"PURCHASING_PRICE" => CCatalogProduct::GetByID($keyOffers)["PURCHASING_PRICE"],
			"PURCHASING_CURRENCY" 	=> "RUB",
		);
		
		if(CCatalogProduct::Add($arFieldsCat))
		{
			echo "�������� ��������� ������ � �������� �������� " . $newID . '<br>';
			 
			$arFieldsPrice = Array(
				"PRODUCT_ID" => $newID,
				"CATALOG_GROUP_ID" => 1,
				"PRICE" => CPrice::GetBasePrice($keyOffers)['PRICE'],
				"CURRENCY" => "RUB",
			);
			CPrice::Add($arFieldsPrice);
		  }
		else
			echo '������ ���������� ���������� ������<br>';
		}
}
echo 'Finish';

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>