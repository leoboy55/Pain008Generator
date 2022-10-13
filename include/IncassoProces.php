<?php

require_once('dbConnection.php');

ini_set('xdebug.var_display_max_depth', 10);
ini_set('xdebug.var_display_max_children', 256);
ini_set('xdebug.var_display_max_data', 1024);

function getCurrentMonthPlusOne(){
    $today = new DateTime("+1 month");
    $date = $today->format("m");
    $dateFormat = "___" . $date;
    $dateOperator = "%";
    $currentMonth = $dateFormat . $dateOperator;

    return $currentMonth;
}

$currentMonth = getCurrentMonthPlusOne();

function getMonthlyClients(){
    $monthContribution = 30.00;

    $conn = newcon();
    $queryMonthly = ("SELECT authorisation_name, authorisation_iban, user_id, authorisation_date FROM ledenbestand WHERE contribution = 'maand' AND member_state = false");
    $resultMonthly = mysqli_query($conn,$queryMonthly);

    if(mysqli_num_rows($resultMonthly) > 0){
        while ($row = mysqli_fetch_assoc($resultMonthly)){
            $allData[] = array ('name' => $row['authorisation_name'],
                'iban' => $row['authorisation_iban'],
                'amount' => $monthContribution,
                'id' => $row['user_id'],
                'date' => $row['authorisation_date']
            );
        }
        mysqli_close($conn);
    }
    return $allData;
}

$allData = getMonthlyClients();


function getHalfYearlyClients($currentMonth, $allData){
    $halfYearContribution = 169.00;

    $conn = newcon();
    $queryHalfYear = ("Select authorisation_name, authorisation_iban, user_id, authorisation_date FROM ledenbestand WHERE authorisation_date LIKE '$currentMonth' AND contribution = 'halfjaar' AND member_state = false");
    $resultHalfYear = mysqli_query($conn, $queryHalfYear);

    if(mysqli_num_rows($resultHalfYear) > 0){
        while ($row = mysqli_fetch_assoc($resultHalfYear)){
            $allData[] = array ('name' => $row['authorisation_name'],
                'iban' => $row['authorisation_iban'],
                'amount' => $halfYearContribution,
                'id' => $row['user_id'],
                'date' => $row['authorisation_date']
            );
        }
        mysqli_close($conn);
    }
    return $allData;
}

$allData = getHalfYearlyClients($currentMonth, $allData);

function getYearlyClients($currentMonth, $allData){
    $yearlyContribution = 320.00;

    $conn = newcon();
    $queryYearly = ("Select authorisation_name, authorisation_iban, user_id, authorisation_date FROM ledenbestand WHERE authorisation_date LIKE '$currentMonth' AND contribution = 'jaar' AND member_state = false");
    $resultYearly = mysqli_query($conn, $queryYearly);

    if(mysqli_num_rows($resultYearly) > 0){
        while ($row = mysqli_fetch_assoc($resultYearly)){
            $allData[] = array ('name' => $row['authorisation_name'],
                'iban' => $row['authorisation_iban'],
                'amount' => $yearlyContribution,
                'id' => $row['user_id'],
                'date' => $row['authorisation_date']
            );
        }
        mysqli_close($conn);
    }
    return $allData ;
}

$allData = getYearlyClients($currentMonth, $allData);

//generate Pain 008 XML File

function createPainXMLFile()
{
    $xmlDoc = new DOMDocument('1.0');
    $xmlDoc->encoding = "UTF-8";
    $document = $xmlDoc->createElement("Document");
    $xmlDoc->appendChild($document);
    $document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');
    $document->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $xmlDoc->save('pain.008.001.02.xml');
    $xmlDoc->load('pain.008.001.02.xml');
    $xmlDoc->preserveWhiteSpace = false;
    $xmlDoc->formatOutput = true;
    echo $xmlDoc->textContent;
    return $xmlDoc;
}

$doc = createPainXMLFile();

function createGroupHeaderInfo($doc, $allData){
    $id = uniqid();
    $date = date("Y-m-d\TH:i:s");
    $totalTransactions = count($allData);
    $total = 0;

    foreach ($allData as $amount){
        $total += $amount["amount"];
    }

    $totalDecimal = sprintf("%.2f", $total);

    $rootTag = $doc->getElementsByTagName("Document")->item(0);

    $cstmrDrctDbtInitn = $doc->createElement("CstmrDrctDbtInitn");
    $grpHdr = $doc->createElement("GrpHdr");
    $msgIdTag = $doc->createElement("MsgId", $id);
    $creDtTm = $doc->createElement("CreDtTm",$date);
    $nbOfTxs = $doc->createElement("NbOfTxs", $totalTransactions);
    $ctrlSum = $doc->createElement("CtrlSum", $totalDecimal);
    $initgPty = $doc->createElement("InitgPty");

    $rootTag->appendChild($cstmrDrctDbtInitn);

    $cstmrDrctDbtInitn->appendChild($grpHdr);

    $grpHdr->appendChild($msgIdTag);
    $grpHdr->appendChild($creDtTm);
    $grpHdr->appendChild($nbOfTxs);
    $grpHdr->appendChild($ctrlSum);
    $grpHdr->appendChild($initgPty);

    $nm = $doc->createElement("Nm");
    $nmText =  $doc->createTextNode("Judo Sensei");

    $initgPty->appendChild($nm);
    $nm->appendChild($nmText);

    $doc->save('pain.008.001.02.xml');
}

createGroupHeaderInfo($doc, $allData);

function createPmtInfInfo($doc, $allData){
    $id = uniqid();
    $totalTransactions = count($allData);
    $executionMonth = date("Y-m");
    $executionDay = "-22";
    $executionDate = $executionMonth . $executionDay;
    $total = 0;

    foreach ($allData as $amount){
        $total += $amount["amount"];
    }

    $totalDecimal = sprintf("%.2f", $total);

    $rootTag = $doc->getElementsByTagName("CstmrDrctDbtInitn")->item(0);

    $pmtInf = $doc->createElement("PmtInf");
    $pmtInfId = $doc->createElement("PmtInfId", $id);
    $pmtMtd = $doc->createElement("PmtMtd", "DD");
    $nbOfTxs = $doc->createElement("NbOfTxs", $totalTransactions);
    $ctrlSum = $doc->createElement("CtrlSum", $totalDecimal);
    $pmtTpInf = $doc->createElement("PmtTpInf");
    $svcLvl = $doc->createElement("SvcLvl");
    $cd = $doc->createElement("Cd", "SEPA");
    $lclInstrm = $doc->createElement("LclInstrm");
    $cd2 = $doc->createElement("Cd", "CORE");
    $seqTp = $doc->createElement("SeqTp", "RCUR");
    $reqdColltnDt = $doc->createElement("ReqdColltnDt", $executionDate );

    $rootTag->appendChild($pmtInf);

    $pmtInf->appendChild($pmtInfId);
    $pmtInf->appendChild($pmtMtd);
    $pmtInf->appendChild($nbOfTxs);
    $pmtInf->appendChild($ctrlSum);
    $pmtInf->appendChild($pmtTpInf);

    $pmtTpInf->appendChild($svcLvl);
    $pmtTpInf->appendChild($lclInstrm);
    $pmtTpInf->appendChild($seqTp);

    $svcLvl->appendChild($cd);
    $lclInstrm->appendChild($cd2);

    $pmtInf->appendChild($reqdColltnDt);

    $doc->save('pain.008.001.02.xml');
}

createPmtInfInfo($doc, $allData);

function createCreditorInfo($doc){
    $rekeningNaam = "Ricardo van der Krans";
    $rekeningNummer = "NL67ABNA0112224377";
    $bic = "ABNANL2A";
    $idCdtr = "NL57ZZZ621557840000";


    $rootTag = $doc->getElementsByTagName("PmtInf")->item(0);

    $cdtr = $doc->createElement("Cdtr");
    $nm = $doc->createElement("Nm", $rekeningNaam);
    $cdtrAcct = $doc->createElement("CdtrAcct");
    $id = $doc->createElement("Id");
    $iban = $doc->createElement("IBAN", $rekeningNummer);
    $ccy = $doc->createElement("Ccy", "EUR");
    $cdtrAgt = $doc->createElement("CdtrAgt");
    $finInstnId = $doc->createElement("FinInstnId");
    $bic = $doc->createElement("BIC", $bic);
    $chrgBr = $doc->createElement("ChrgBr", "SLEV");

    $cdtrSchmeId = $doc->createElement("CdtrSchmeId");
    $cdtrIdHolder = $doc->createElement("Id");
    $prvtId = $doc->createElement("PrvtId");
    $othr = $doc->createElement("Othr");
    $cdtrId = $doc->createElement("Id", $idCdtr);
    $schmeNm = $doc->createElement("SchmeNm");
    $prtry = $doc->createElement("Prtry", "SEPA");

    $rootTag->appendChild($cdtr);
    $rootTag->appendChild($cdtrAcct);
    $rootTag->appendChild($cdtrAgt);
    $rootTag->appendChild($chrgBr);

    $cdtr->appendChild($nm);
    $cdtrAcct->appendChild($id);
    $cdtrAcct->appendChild($ccy);
    $cdtrAgt->appendChild($finInstnId);

    $id->appendChild($iban);
    $finInstnId->appendChild($bic);

    $rootTag->appendChild($cdtrSchmeId);
    $cdtrSchmeId->appendChild($cdtrIdHolder);
    $cdtrIdHolder->appendChild($prvtId);
    $prvtId->appendChild($othr);
    $othr->appendChild($cdtrId);
    $othr->appendChild($schmeNm);
    $schmeNm->appendChild($prtry);


    $doc->save('pain.008.001.02.xml');
}

createCreditorInfo($doc);


function createClientsIncasso($doc, $allData){
    $costumderId2 = "01";
    $mandaat = "MANDAAT";

    $bicRabo = "RABONL2U";
    $bicIng = "INGBNL2A";
    $bicAbn = "ABNANL2A";
    $bicKnab = "KNABNL2H";
    $bicBunq = "BUNQNL2A";
    $bicSns = "SNSBNL2A";
    $bicTriodos = "TRIONL2U";
    $bicAsn = "ASNBNL21";
    $bicRegio = "RBRBNL21";
    $othrIdSample = "NOTPROVIDED";


    $rootTag = $doc->getElementsByTagName("PmtInf")->item(0);

    foreach($allData as $client){
        $dateFormatted = $client["date"];

        $dayDate = substr($dateFormatted, 0,2);
        $monthDate = substr($dateFormatted, 3,3);
        $yearDate = substr($dateFormatted, 6,5);

        $newDate = $yearDate . "-" . $monthDate . $dayDate;

        $id_ = uniqid();
        $clientId = $mandaat . $client["id"] . $costumderId2;
        $amountDecimal = sprintf("%.2f", $client["amount"]);

        $drctDbtTxInf = $doc->createElement("DrctDbtTxInf");
        $pmtId = $doc->createElement("PmtId");
        $endToEndId = $doc->createElement("EndToEndId", $id_);
        $instdAmt = $doc->createElement("InstdAmt", $amountDecimal);
        $drctDbtTx = $doc->createElement("DrctDbtTx");
        $mndtRltdInf = $doc->createElement("MndtRltdInf");

        $mndtId = $doc->createElement("MndtId", $clientId);
        $dtOfSgntr = $doc->createElement("DtOfSgntr", $newDate);

        $dbtrAgt = $doc->createElement("DbtrAgt");
        $finInstnId = $doc->createElement("FinInstnId");

        //append right BIC code
      if(strpos($client["iban"], "RABO")) {
          $bic = $doc->createElement("BIC", $bicRabo);
      } else if (strpos($client["iban"], "INGB")){
          $bic = $doc->createElement("BIC", $bicIng);
      } else if (strpos($client["iban"], "ABNA")){
          $bic = $doc->createElement("BIC", $bicAbn);
      } else if (strpos($client["iban"], "KNAB")){
          $bic = $doc->createElement("BIC", $bicKnab);
      } else if (strpos($client["iban"], "BUNQ")){
          $bic = $doc->createElement("BIC", $bicBunq);
      } else if (strpos($client["iban"], "SNSB")){
          $bic = $doc->createElement("BIC", $bicSns);
      } else if (strpos($client["iban"], "TRIO")){
          $bic = $doc->createElement("BIC", $bicTriodos);
      } else if (strpos($client["iban"], "ASNB")){
          $bic = $doc->createElement("BIC", $bicAsn);
      } else if (strpos($client["iban"], "RBRB")){
          $bic = $doc->createElement("BIC", $bicRegio);
      } else {
          $bic = $doc->createElement("BIC", $othrIdSample);
      }


        $dbtr = $doc->createElement("Dbtr");
        $nmClient = $doc->createElement("Nm", $client["name"]);
        $dbtrAcct = $doc->createElement("DbtrAcct");
        $dbtrId = $doc->createElement("Id");
        $iban = $doc->createElement("IBAN", $client["iban"]);

        $rootTag->appendChild($drctDbtTxInf);

        $drctDbtTxInf->appendChild($pmtId);
        $pmtId->appendChild($endToEndId);

        $attribute = $drctDbtTxInf->appendChild($instdAmt);
        $attribute->setAttribute('Ccy', 'EUR');

        $drctDbtTxInf->appendChild($drctDbtTx);
        $drctDbtTx->appendChild($mndtRltdInf);
        $mndtRltdInf->appendChild($mndtId);
        $mndtRltdInf->appendChild($dtOfSgntr);


        $drctDbtTxInf->appendChild($dbtrAgt);
        $dbtrAgt->appendChild($finInstnId);
        $finInstnId->appendChild($bic);

        $drctDbtTxInf->appendChild($dbtr);
        $dbtr->appendChild($nmClient);

        $drctDbtTxInf->appendChild($dbtrAcct);
        $dbtrAcct->appendChild($dbtrId);
        $dbtrId->appendChild($iban);


    }
    $doc->save('pain.008.001.02.xml');

    ob_end_clean();
    header_remove();

    $xmlFilename = "pain.008.001.02.xml";
    $today = new DateTime("+1 month");
    $date = $today->format("M-Y");
    $xmlTempName = $date . ".xml";

    header("X-Sendfile: {$xmlFilename}");
    header('Content-type: text/xml');
    header('Content-Disposition: attachment; filename=' . $xmlTempName);
    readfile($xmlFilename);

    $doc->removeChild($doc->documentElement);

    $doc->save('pain.008.001.02.xml');
}

createClientsIncasso($doc, $allData);