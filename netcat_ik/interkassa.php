<?

class nc_payment_system_interkassa extends nc_payment_system {

	const ERROR_MNT_ID_IS_NOT_VALID = NETCAT_MODULE_PAYMENT_INTERKASSA_ERROR_MNT_ID_IS_NOT_VALID;

    const TARGET_URL = "https://sci.interkassa.com/";

    protected $automatic = TRUE;

    // принимаемые валюты
    protected $accepted_currencies = array('RUB', 'RUR');

    // параметры сайта в платежной системе
    protected $settings = array(
        'IK_CO_ID' => null,
        'SECRET_KEY' => null,
        'TEST_KEY' => null,
        );

    // передаваемые параметры
    protected $request_parameters = array( // 'InvId' => null,
        // 'InvDesc' => null,
        );



    // получаемые параметры
    protected $callback_response = array(

        'PARAMETERS' => null
        );

    /**
     *
     */
    public function execute_payment_request(nc_payment_invoice $invoice) {
        $amount = $invoice->get_amount("%0.2F");
        $currency = ($invoice->get_currency() == 'RUR') ? 'RUB' : $invoice->get_currency();

        $arg = array(
            'ik_cur'=>$currency,
            'ik_co_id'=>$this->get_setting('IK_CO_ID'),
            'ik_pm_no'=>$invoice->get_id(),
            'ik_am'=>$amount,
            'ik_desc'=>'#'.$invoice->get_id(),
            );

        $dataSet = $arg;
        ksort($dataSet, SORT_STRING);
        array_push($dataSet, $this->get_setting('SECRET_KEY'));
        $signString = implode(':', $dataSet);
        $sign = base64_encode(md5($signString, true));


        ob_end_clean();
        $form = "
        <html>
        <body>
            <form name='payment' action='https://sci.interkassa.com/' method='post' accept-charset='utf-8'>" .
                $this->make_inputs(array(
                    'ik_co_id' => $this->get_setting('IK_CO_ID'),
                    'ik_pm_no' => $invoice->get_id(),
                    'ik_am' => $amount,
                    'ik_cur' => $currency,
                    'ik_desc' => '#'.$invoice->get_id(),
                    'ik_sign' => $sign,
                    )) . "
            </form>
            <script>
              document.forms[0].submit();
          </script>
      </body>
      </html>";
      echo $form;
      exit;
    }

      public function on_response(nc_payment_invoice $invoice = null) {

        $this->wrlog('on_response');

        $invoice->set('status', nc_payment_invoice::STATUS_SUCCESS);
        $invoice->save();
        $this->on_payment_success($invoice);
    }


    public function validate_payment_request_parameters() {

        $this->wrlog('validate_payment_request_parameters');

        if (!$this->get_setting('IK_CO_ID')) {
            $this->add_error(nc_payment_system_interkassa::ERROR_MNT_ID_IS_NOT_VALID);
        }
    }

    public function validate_payment_callback_response(nc_payment_invoice $invoice = null) {

     $this->wrlog('validate_payment_callback_response');

     foreach ($_REQUEST as $key => $value) {
         $str = $key.' => '.$value;
         $this->wrlog($str);
     }
     $this->wrlog('--------');


         if(count($_REQUEST) && $this->get_setting('IK_CO_ID') == $_REQUEST['ik_co_id']){

            $this->wrlog('params ok');

            if ($_REQUEST['ik_inv_st'] == 'success'){

                $this->wrlog('success');

                if(isset($_REQUEST['ik_pw_via']) && $_REQUEST['ik_pw_via'] == 'test_interkassa_test_xts'){
                    $secret_key = $this->get_setting('TEST_KEY');
                } else {
                    $secret_key = $this->get_setting('SECRET_KEY');
                }
                $this->wrlog($secret_key);



                $request_sign = $_REQUEST['ik_sign'];

                $dataSet = [];

                foreach ($_REQUEST as $key => $value) {
                    if (!preg_match('/ik_/', $key)) continue;
                    $dataSet[$key] = $value;
                }

                unset($dataSet['ik_sign']);
                ksort($dataSet, SORT_STRING); 
                array_push($dataSet, $secret_key);  
                $signString = implode(':', $dataSet); 
                $sign = base64_encode(md5($signString, true)); 

                if($request_sign != $sign){
                    $this->wrlog('Подписи не совпадают!');
                    die('Подписи не совпадают!');

                }
            }

        }else{


            $this->wrlog('params didnt match');
            die('params didnt match');
        }


    }

    public function load_invoice_on_callback() {

        $this->wrlog('load_invoice_on_callback');

        return $this->load_invoice($this->get_response_value('ik_pm_no'));
    }

    public function wrlog($content){
        $file = 'log.txt';
        $doc = fopen($file, 'a');
        file_put_contents($file, PHP_EOL . $content, FILE_APPEND);  
        fclose($doc);
    }
}

