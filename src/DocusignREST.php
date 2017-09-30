<?php

namespace Daniilkrok\DocusignClient;

class DocusignREST
{
    private $header;
    const LOG_PATH = __DIR__."/logs/error.log";

    private $host;              // host for DocuSign sandbox or production. SANDBOX: https://demo.docusign.net
    private $email;			    // your account email
    private $password;		    // your account password
    private $integratorKey;		// your account integrator key, found on (Preferences -> API page)
    private $recipientName;		// provide a recipient (signer) name
    private $recipientEmail;	// provide a recipient (signer) email
    private $templateId;		// provide a valid templateId of a template in your account
    private $templateRoleName;	// use same role name that exists on the template in the console
    private $clientUserId;		// to add an embedded recipient you must set their clientUserId property in addition to
                                // the recipient name and email.  Whatever you set the clientUserId to you must use the same
                                // value when requesting the sending URL
    private $accountId;
    private $baseUrl;
    private $envelopeId;
    private $embeddedViewURL;

    function __construct($param)
    {
        // Input your info:
        $this->host = $param['host'];
        $this->email = $param['email'];
        $this->password = $param['password'];
        $this->integratorKey = $param['integrator_key'];
        $this->templateRoleName = "signers";
        $this->clientUserId = $this->email;

        // construct the authentication header:
        $this->header = "<DocuSignCredentials><Username>" . $this->email . "</Username><Password>" . $this->password . "</Password><IntegratorKey>" . $this->integratorKey . "</IntegratorKey></DocuSignCredentials>";

        $this->login();
    }

    public function addRecipient($param) {
        $this->recipientName = $param['recipient_name'];
        $this->recipientEmail = $param['recipient_email'];
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////
    // STEP 1 - Login (retrieves baseUrl and accountId)
    /////////////////////////////////////////////////////////////////////////////////////////////////
    public function login() {
        $url = "https://demo.docusign.net/restapi/v2/login_information";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("X-DocuSign-Authentication: $this->header"));
        $json_response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ( $status != 200 ) {
            $error = "HTTP Status Code: " . $status . "\n".$json_response;
            $this->Error($error);
        }
        $response = json_decode($json_response, true);
        $accountId = $response["loginAccounts"][0]["accountId"];
        $baseUrl = $response["loginAccounts"][0]["baseUrl"];
        curl_close($curl);
        //--- display results
        //echo "accountId = " . $accountId . "\nbaseUrl = " . $baseUrl . "\n";
        $this->accountId = $accountId;
        $this->baseUrl = $baseUrl;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////
    // STEP 2 - Create an envelope with an Embedded recipient (uses the clientUserId property)
    /////////////////////////////////////////////////////////////////////////////////////////////////
    public function createEnvelopeUseTemplate($templateID) {
        $this->templateId = $templateID;
        $data = array(
            "status" => "sent",
            "accountId" => $this->accountId,
            "emailSubject" => "DocuSign API - Embedded Sending Example",
            "templateId" => $this->templateId,
            "templateRoles" => array(
                array(
                    "roleName" => $this->templateRoleName,
                    "email" => $this->recipientEmail,
                    "name" => $this->recipientName,
                    "recipientId" => "1",
                    "clientUserId" => $this->clientUserId,
                    "tabs" => array(
                        "textTabs" => array(
                            array(
                                "tabLabel"=> "name",
                                "value" => "TEST TEXT"
                            ),
                            array(
                                "tabLabel"=> "address",
                                "value" => "TEXT"
                            )
                        )
                    )
                )
            )
        );
        $data_string = json_encode($data);
        $curl = curl_init($this->baseUrl . "/envelopes" );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                "X-DocuSign-Authentication: $this->header" )
        );
        $json_response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ( $status != 201 ) {
            $error = "HTTP Status Code: " . $status . "\n".$json_response;
            $this->Error($error);
        }
        $response = json_decode($json_response, true);
        $envelopeId = $response["envelopeId"];
        curl_close($curl);
        //--- display results
        //echo "Envelope created! Envelope ID: " . $envelopeId . "\n";
        $this->envelopeId = $envelopeId;
    }

    public function createEnvelopeUseFile($file_path) {
        $file_name = basename($file_path);
        /*$data = array(
            "status" => "sent",
            "accountId" => $this->accountId,
            "emailSubject" => "DocuSign API - Embedded Sending Example",
            "documents" => array(
                array(
                    "documentId" => "1",
                    "name" => "test.pdf",
                    "transformPdfFields" => "true"
                )
            ),
            "recipients" => array(
                "signers" => array(
                    array(
                        "email" => $this->recipientEmail,
                        "name" => $this->recipientName,
                        "recipientId" => "1",
                        "tabs" => array(
                            "textTabs" => array(
                                array(
                                    "tabLabel"=> "name\\*",
                                    "value" => "$ 100",
                                    //"locked"=> "true",
                                    //"documentId"=> "1",
                                    //"pageNumber"=> "1"
                                )
                            )
                        )
                    )
                )
            )
        );*/
        $data = array(
            "status" => "sent",
            "emailSubject" => "DocuSign API - Embedded Sending Example",
            "compositeTemplates" => array(
                array(
                    "inlineTemplates" => array(
                        array(
                            "sequence" => 1,
                            "recipients" => array(
                                "signers" => array(
                                    array(
                                        "email" => $this->recipientEmail,
                                        "name" => $this->recipientName,
                                        "recipientId" => "1",
                                        "clientUserId" => $this->clientUserId,
                                        "routingOrder" => "1",
                                        "tabs" => array(
                                            "textTabs" => array(
                                                array(
                                                    "tabLabel"=> "Life1_name",
                                                    "value" => "Signer Onee",
                                                    "locked"=> true,
                                                    "fontColor"=> "BrightBlue",
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
                                        )
                                    )
                                )
                            )
                        )
                    ),
                    "document" => array(
                        "documentId" => 1,
                        "name" => $file_name,
                        "transformPdfFields" => "true"
                    )
                )
            )
        );

        $data = json_encode($data);

        $file_contents = file_get_contents($file_path);
        $data_string = "\r\n"
            ."\r\n"
            ."--myboundary\r\n"
            ."Content-Type: application/json\r\n"
            ."Content-Disposition: form-data\r\n"
            ."\r\n"
            ."$data\r\n"
            ."--myboundary\r\n"
            ."Content-Type:application/pdf\r\n"
            ."Content-Disposition: file; filename=\"$file_name\"; documentid=1 \r\n"
            ."\r\n"
            ."$file_contents\r\n"
            ."--myboundary--\r\n"
            ."\r\n";
        
        $curl = curl_init($this->baseUrl . "/envelopes" );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Content-Type: multipart/form-data;boundary=myboundary',
                'Content-Length: ' . strlen($data_string),
                "X-DocuSign-Authentication: $this->header" )
        );
        $json_response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ( $status != 201 ) {
            $error = "HTTP Status Code: " . $status . "\n".$json_response;
            $this->Error($error);
        }
        $response = json_decode($json_response, true);
        $envelopeId = $response["envelopeId"];
        curl_close($curl);
        //--- display results
        //echo "Envelope created! Envelope ID: " . $envelopeId . "\n";
        $this->envelopeId = $envelopeId;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////
    // STEP 3 - Get the Embedded Sending View (aka the "tag-and-send" view)
    /////////////////////////////////////////////////////////////////////////////////////////////////
    public function createEmbeddedViewUrl() {
        $data = array(
            "userName" => $this->recipientName,
            "email" => $this->recipientEmail,
            "recipientId" => "1",
            "clientUserId" => $this->clientUserId,
            "authenticationMethod" => "email",
            "returnUrl" => "http://www.docusign.com/devcenter"
        );
        $data_string = json_encode($data);
        $curl = curl_init($this->baseUrl . "/envelopes/$this->envelopeId/views/recipient" ); //sender
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                "X-DocuSign-Authentication: $this->header" )
        );
        $json_response = curl_exec($curl);
        $response = json_decode($json_response, true);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ( $status != 201 ) {
            $error = "HTTP Status Code: " . $status . "\n".$json_response;
            $this->Error($error);
        }
        $url = $response["url"];
        //--- display results
        //echo "Embedded URL is: \n\n" . $url . "\n\nNavigate to this URL to start the tag-and-send view of the envelope\n";
        $this->embeddedViewURL = $url;
    }
    
    public function generateTabsData() {
        
    }

    /**
     * @return mixed
     */
    public function getEnvelopeId()
    {
        return $this->envelopeId;
    }

    /**
     * @return mixed
     */
    public function getEmbeddedViewURL()
    {
        return $this->embeddedViewURL;
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