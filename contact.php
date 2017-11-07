<?php

class MPCE_CFA_Mailer{
    private $mailPrepared;

    private $mail;
    private $subject;

    private $attachments;
    private $errors;
    private $from;
    private $to;

    public function __construct( $from, $to, $subj ){
        $this->from = $from;
        $this->to = $to;
        $this->subject = $subj;
    }

    public function prepareMail( $post ){
        $this->errors = array();
        $response = true;

        if( array_key_exists ( 'g-recaptcha-response', $post ) ) {
            $response = $this->responseReCAPTCHA($post['g-recaptcha-response']);
        }

         if ($response === true) {

            unset($post['g-recaptcha-response']);
            unset($post['cfa-submit']);
            unset($post['cfa_name']);
            unset($post['action']);
            unset($post['security']);

             $templates = unserialize(stripslashes('a:0:{}'));
            if( isset($templates[ $post['cfa_id'] ]) ){
                $template =  trim($templates[ $post['cfa_id'] ]);
            } else {
                $template = false;
            }

             if( $template ){
                 $mail = $this->generateByTemplate( $post, $template);
             }  else {
                 unset($post['cfa_id']);
                 $mail = $this->generateByDefault($post);
             }

            if ( count($this->errors) === 0 ){
                $this->mailPrepared = true;
                $this->mail = $mail;

                return true;
            }
        }

        return false;
    }

    public function sendMailWithAttach( $path='', $filename=''){


        if($path === ''){
            $headers = '';
            $headers .= "From: " . $this->from . "" . PHP_EOL;
            $headers .= "Reply-To: " . $this->from . "" . PHP_EOL;
            $headers .= "Return-Path: " . $this->from . "" . PHP_EOL;
            $headers .= "Content-Type: text/html; charset=UTF-8" . PHP_EOL;

            //Send the email
            $sended = mail( $this->to, $this->subject, $this->mail, $headers);

            if ( $sended ) {
                return true;
            }

            $this->errors = 'Function wp_mail returned false.';
        }

        return false;
    }

    public function sendMail(){
        if( !$this->mailPrepared) return false;

        if( count( $this->attachments ) > 0 ){
            return $this->sendMailWithAttach($this->attachments);
        }

        return $this->sendMailWithAttach();
    }

    public function generateByTemplate( $post, $template){
        $mail = $template;
        foreach( $post as $key => $value ){
            $replace = array();
            if(is_array($value)){
                foreach($value as $numb => $val){
                    $val =  $this->protectString($val);
                    $replace [$numb] = $val;
                }
                $replace = implode(',', $replace);
            } else{
                $replace = $this->protectString($value);
            }
            $mail = preg_replace( '/\[' . $key . '\]/', $replace, $mail);
        }

        return $mail;
    }

    public function generateByDefault( $post ){
        $mail = "";
        foreach($post as $key=>$val){
            $mail .= "<p>";
            $replace = array();

            if(is_array($val)){
                foreach($val as $numb => $value){
                    $replace[$numb] = $this->protectString($value);
                }
                $replace = implode(',', $replace);
            } else{
                $replace =  $this->protectString($val);
            }
            $mail .= '<b>' . $this->protectString($key) . '</b>' . '<br />';
            $mail .= $replace;

            $mail .= "</p>";
        }

        return $mail;
    }

/*
 * return true if reCAPTCHA submit 'not robot'
 * */
    private function responseReCAPTCHA( $recaptcha ){
        $captcha = '';
        $settings = unserialize(stripslashes('a:8:{s:20:\"mpce_cfa_mail_sender\";s:24:\"marcelmarin256@gmail.com\";s:23:\"mpce_cfa_mail_recipient\";s:24:\"marcelmarin256@gmail.com\";s:21:\"mpce_cfa_mail_subject\";s:28:\"%form-name% from %blog-name%\";s:21:\"mpce_cfa_mail_success\";s:38:\"Sender\'s message was sent successfully\";s:18:\"mpce_cfa_mail_fail\";s:35:\"Sender\'s message was failed to send\";s:17:\"recaptch_site_key\";s:0:\"\";s:19:\"recaptch_secret_key\";s:0:\"\";s:13:\"recaptch_lang\";s:0:\"\";}'));

        if (isset($recaptcha)) {
            $captcha = $recaptcha;
        }
        if (!$captcha) {
            $this->errors[] = 'Please check the reCAPTCHA.';
            return false;
        }

        $url = "https://www.google.com/recaptcha/api/siteverify?secret=" . $settings['recaptch_secret_key']
            . "&response=" . $captcha
            . "&remoteip=" . $_SERVER['REMOTE_ADDR'];

        $args = array(
            'timeout'     => 15,
            'sslverify'   => false,
        );
        $response = curl_call( $url, $args );

        try {
            $json = json_decode( $response );
        } catch ( Exception $ex ) {
            $json = null;
        }

        $response = $json->success;

        if ($response !== true) {
            $this->errors[] = 'ReCAPTCHA Error';
        }

        return $response;
    }

    private function protectString($value){
        return htmlspecialchars(stripslashes(trim($value)));
    }

    /**
     * @return errors rised during prepareing mail
     */
    public function getErrors(){
        return implode(",",  (array)$this->errors);
    }

    public function set_html_content_type(){
        return "text/html";
    }

    public function set_message_from(){
        return $this->from;
    }

}

// Make a curl call
function curl_call($url, $post = array()){	
	
	// Set the curl parameters.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
		
	// Connection Time OUT
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	
	// You can timeout in one hour max
	curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
	
	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		
	// UserAgent and Cookies
	curl_setopt($ch, CURLOPT_USERAGENT, 'Contact-Form');
	
	if(!empty($post)){
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	}
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Get response from the server.
	$resp = curl_exec($ch);
	$curl_err = curl_error($ch);
	curl_close($ch);
	
	if(empty($resp)){
		return false;
	}
	
	return $resp;
	
}

function sm_send_json( $response ) {
	@header( 'Content-Type: application/json; charset=UTF-8');
	echo json_encode( $response );
	die();
}


function mpce_cfa_contact_ajax(){
    ob_start();
    $json = array('errors' => array(), 'success' => '');

    if(empty($_POST)){
		$json['success'] = false;
		$json['errors'] = array('Security error!');

		ob_clean();
		sm_send_json($json);
		die();
    }

    $settings = unserialize(stripslashes('a:8:{s:20:\"mpce_cfa_mail_sender\";s:24:\"marcelmarin256@gmail.com\";s:23:\"mpce_cfa_mail_recipient\";s:24:\"marcelmarin256@gmail.com\";s:21:\"mpce_cfa_mail_subject\";s:28:\"%form-name% from %blog-name%\";s:21:\"mpce_cfa_mail_success\";s:38:\"Sender\'s message was sent successfully\";s:18:\"mpce_cfa_mail_fail\";s:35:\"Sender\'s message was failed to send\";s:17:\"recaptch_site_key\";s:0:\"\";s:19:\"recaptch_secret_key\";s:0:\"\";s:13:\"recaptch_lang\";s:0:\"\";}'));
    $replacements = array(
        'blogname' => array(
            'search' => '%blog-name%',
            'replace' => 'My Website'
        ),
        'formname' => array(
            'search' => '%form-name%',
            'replace' => $_POST['cfa_name']
        ),
    );

    $from = trim($settings['mpce_cfa_mail_sender']);
    $to = trim($settings['mpce_cfa_mail_recipient']);
    $subj = trim($settings['mpce_cfa_mail_subject']);

    foreach( $replacements as $key => $value ){
        $subj = str_replace( $value['search'], $value['replace'], $subj);
    }

    $to = ($to === '') ? 'marcelmarin256@gmail.com' : $to;
    $from = ($from === '') ? 'marcelmarin256@gmail.com' : $from;

    $mailer = new MPCE_CFA_Mailer( $from, $to, $subj );
    $mailer->prepareMail( $_POST );

    if(!$mailer->getErrors()) {
        $send = $mailer->sendMail();
    }

    if( $send ){
        $json['success'] = true;
    } else {
        $json['success'] = false;
        $json['errors'] = $mailer->getErrors();
    }

    ob_clean();
    sm_send_json($json);
}

if(!empty($_POST)){
	mpce_cfa_contact_ajax();
}