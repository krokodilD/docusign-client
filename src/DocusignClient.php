<?php
/**
 * Created by PhpStorm.
 * User: Daniil Krok
 * Date: 19.09.2017
 */

namespace Daniilkrok\DocusignClient;

class DocusignClient
{
    const LOG_PATH = __DIR__."/logs/error.log";

    private $fieldsDataArray;
    private $requestData;

    function __construct()
    {
        putenv ("DK_DUS_HOST=https://demo.docusign.net");
        putenv ("DK_DUS_INTEGRATOR_KEY=9562227d-4144-46ea-863d-6c8abeea199f");
        putenv ("DK_DUS_LOGIN=krokodild@gmail.com");
        putenv ("DK_DUS_PASSWORD=KrokodilD9");
        putenv ("DK_DUS_ADMIN_EMAIL=krokodild@gmail.com");

        $entityBody = file_get_contents('php://input');
        $this->requestData = json_decode($entityBody);
    }

    public function dusRun()
    {
        //try {
            $DUS = new DocusignREST([
                'host' => env("DK_DUS_HOST"),
                'integrator_key' => env("DK_DUS_INTEGRATOR_KEY"),
                'email' => env("DK_DUS_LOGIN"),
                'password' => env("DK_DUS_PASSWORD"),
            ]);

            $DUS->addRecipient([
                'recipient_name' => $this->requestData->recipient_name,
                'recipient_email' => $this->requestData->recipient_email
            ]);

            if ($this->requestData->method == 'template')
                $DUS->createEnvelopeUseTemplate($this->processData($this->requestData->template_set));

            if ($this->requestData->method == 'file')
                $DUS->createEnvelopeUseFile($this->processData($this->requestData->files_set));

            $DUS->createEmbeddedViewUrl();

            echo json_encode([
                "envelope_id" => $DUS->getEnvelopeId(),
                "embedded_view_url" => $DUS->getEmbeddedViewURL()
            ]);
        /*} catch (\Exception $e) {
            $this->Error($e->getMessage());
        }*/
    }

    public function processData($sets) {
        if ($this->requestData->method == 'template')
            $sets->data = $this->fieldsMapping($sets->data);

        if ($this->requestData->method == 'file')
            foreach ($sets as $key => $set) {
                $sets[$key]->data = $this->fieldsMapping($set->data);
            }
        return $sets;
    }

    public function fieldsMapping($object)
    {
        /*
         EXAMPLE DUS FIELDS DATA:
         array(
            "textTabs" => array(
                array(
                    "tabLabel"=> "Life1_name",
                    "value" => "Signer Onee",
                    "locked"=> true
                )
            ),
            "checkboxTabs" => array(
                array(
                    "tabLabel"=> "Rnb_her",
                    "selected"=> true,
                    "locked"=> true,
                ),
                array(
                    "tabLabel"=> "Rnb_ltcr",
                    "selected"=> true,
                    "locked"=> true,
                )
            ),
            "radioGroupTabs" => array(
                array(
                    "groupName" => "Life1_sex",
                    "radios" => array(
                        array(
                            "value" => "M",
                            "selected"=> true,
                            "locked"=> true
                        )
                    )
                )
            )
        );
        */

        $template = __DIR__ . '/maps/' . $this->requestData->map_template . '.map';
        $template = file($template);

        foreach($template as $key => $line) {
            $lineData = explode('=', trim($line));
            $tabName = $lineData[0];
            $tabValue = '';
            $secondPart = explode('|', trim($lineData[1]));
            $mapping = explode(',', $secondPart[0]);
            $type = $secondPart[1];
            $options = isset($secondPart[2]) ? explode('-', $secondPart[2]) : false;
            //var_dump($tabName);
            //var_dump($mapping);
            //var_dump($type);
            //var_dump($options);

            if ($type){
                switch ($type) {
                    case 'text':
                        foreach ($mapping as $map) {
                            $tabValue .= $this->getValueByMap($object, $map).' ';
                        }
                        if (trim($tabValue) == '')
                            break;
                        if ($options) {
                            $tabValue = substr($tabValue, $options[0], $options[1]); //cut the string option
                            if (isset($options[2]) && $options[2] == "month") {
                                $tabValue = date('M', mktime(0, 0, 0, $tabValue, 10));
                            }
                        }
                        $this->addTextTab($tabName, $tabValue);
                        break;
                    case 'radiogroup':
                        foreach ($mapping as $map) {
                            $tabValue = $this->getValueByMap($object, $map);
                        }
                        $this->addRadioGroupTab($tabName, $tabValue);
                        break;
                    case 'checkbox':
                        //var_dump($tabName); exit();
                        $this->addCheckboxTab($tabName);
                        break;
                }
            }

            /*
            if (strripos($lineData[1], '|') !== false) {
                $valueData = explode('|', trim($lineData[1]));
                $map = str_replace('/', '->', $valueData[0]);
                $option = $valueData[1];

                $value = '';
                if ($option){
                    $optionData = explode('*', trim($option));
                    switch ($optionData[0]) {
                        case 'substr':
                            $value = substr($this->getValueByMap($fieldsData, $map), $optionData[1], $optionData[2]);
                            break;
                        case 'radiobutton':
                            $oValue = $this->getValueByMap($fieldsData, $map);
                            foreach ($optionData as $key => $item) {
                                if ($key == 0) continue;
                                if (strtolower($oValue) == strtolower($item)) {
                                    $radiobuttonKey = $key-1;
                                    //$field .= '#'.$radiobuttonKey;
                                    $value = 'Yes';
                                }
                            }
                            break;
                    }
                }
                $fieldsDataArray[$field] = $value;
            }else{
                $map = str_replace('/', '->', $lineData[1]);
                $fieldsDataArray[$field] = $this->getValueByMap($fieldsData, $map);
            }*/
        }
        //var_dump($this->fieldsDataArray); exit();
        return $this->fieldsDataArray;
    }

    function addTextTab($tabName, $tabValue) {
        $this->fieldsDataArray['textTabs'][] = [
            "tabLabel"=> $tabName,
            "value" => trim($tabValue),
            "locked"=> true,
            "fontColor"=> "Purple",
            "fontSize"=> "Size14",
        ];
    }
    function addCheckboxTab($tabName) {
        $this->fieldsDataArray['checkboxTabs'][] = [
            "tabLabel"=> $tabName,
            "selected"=> true,
            "locked"=> true,
        ];
    }
    function addRadioGroupTab($tabName, $tabValue) {
        $this->fieldsDataArray['radioGroupTabs'][] = [
            "groupName" => $tabName,
            "radios" => [
                [
                    "value" => $tabValue,
                    "selected"=> true,
                    "locked"=> true
                ]
            ]
        ];
    }

    function getValueByMap($obj, $path_str)
    {
        $val = null;

        $path = preg_split('/->/', $path_str);
        $node = $obj;
        while (($prop = array_shift($path)) !== null) {
            if (!is_object($obj) || !property_exists($node, $prop)) {
                $val = null;
                break;
            }
            $val = $node->$prop;
            // TODO: Insert any logic here for cleaning up $val

            $node = $node->$prop;
        }

        return $val;
    }

    function Error($msg) {
        //--------------------
        error_log("(".date('d/m/Y H:i:s').") ".$msg."\n", 3, self::LOG_PATH);
        // send email
        //TODO: better email delivery
        mail(env("DK_DUS_ADMIN_EMAIL"), 'Error DocuSign sending PDF', $msg);
        die($msg);
        //die('<b>FPDF-Merge Error:</b> '.$msg);
    }
}