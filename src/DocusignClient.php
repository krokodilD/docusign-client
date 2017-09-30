<?php
/**
 * Created by PhpStorm.
 * User: Daniil Krok
 * Date: 19.09.2017
 */

namespace Daniilkrok\DocusignClient;

class DocusignClient
{
    function __construct()
    {
        putenv ("DK_DUS_HOST=https://demo.docusign.net");
        putenv ("DK_DUS_INTEGRATOR_KEY=9562227d-4144-46ea-863d-6c8abeea199f");
        putenv ("DK_DUS_LOGIN=krokodild@gmail.com");
        putenv ("DK_DUS_PASSWORD=KrokodilD9");
        putenv ("DK_DUS_ADMIN_EMAIL=krokodild@gmail.com");
    }

    public function index()
    {
        $entityBody = file_get_contents('php://input');
        $requestData = json_decode($entityBody);

        $result = $this->sendToDocusign([
            'pdf_file' => $requestData->docusign->recipient_name,
            'tabsData' => $requestData->docusign->recipient_name,
            'recipient_name' => $requestData->docusign->recipient_name,
            'recipient_email' => $requestData->docusign->recipient_email,
        ]);

        echo $result;
    }

    public function sendToDocusign($data)
    {
        $DUS = new DocusignREST([
            'host' => env("DK_DUS_HOST"),
            'integrator_key' => env("DK_DUS_INTEGRATOR_KEY"),
            'email' => env("DK_DUS_LOGIN"),
            'password' => env("DK_DUS_PASSWORD"),
        ]);
        $DUS->addRecipient([
            'recipient_name' => $data['recipient_name'],
            'recipient_email' => $data['recipient_email']
        ]);
        //$DUS->createEnvelopeUseTemplate("d61a246f-f17a-4ba5-b27f-6a71d0f70583");
        $DUS->createEnvelopeUseFile(base_path("ICC16 NB6000_022017.pdf"));
        $DUS->createEmbeddedViewUrl();

        return json_encode([
            "envelope_id" => $DUS->getEnvelopeId(),
            "embedded_view_url" => $DUS->getEmbeddedViewURL()
        ]);
    }

    public function fieldsMapping($object)
    {
        $fieldsData = $object->data;
        $template = __DIR__ . '/../pdf_templates/' . $object->mapping_template . '.map';
        $template = file($template);

        $fieldsDataArray = [];
        foreach($template as $key => $line) {
            $lineData = explode('=', trim($line));
            $field = $lineData[0];
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
            }
        }

        return $fieldsDataArray;
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
}