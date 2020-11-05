<?php

if (class_exists('Rcl_Payment')) {

add_action('init','rcl_add_nixmoney_payment');
function rcl_add_nixmoney_payment(){
    $pm = new NixMoney_Payment();
    $pm->register_payment('nixmoney');
}

class NixMoney_Payment extends Rcl_Payment{

    public $form_pay_id;

    function register_payment($form_pay_id){

        $this->form_pay_id = $form_pay_id;

        parent::add_payment($this->form_pay_id, array(
            'class'=>get_class($this),
            'request'=>'NM_TYPE_PAY',
            'name'=>'NixMoney',
            'image'=>rcl_addon_url('assets/nixmoney.jpg',__FILE__)
        ));

        if(is_admin()) $this->add_options();

    }

    function add_options(){
        add_filter('rcl_pay_option',(array($this,'options')));
        add_filter('rcl_pay_child_option',(array($this,'child_options')));
    }

    function options($options){
        $options[$this->form_pay_id] = 'NixMoney';
        return $options;
    }

    function child_options($child){
        global $rmag_options;

        $opt = new Rcl_Options();

        $curs = array( 'USD', 'EUR' );

        if(false !== array_search($rmag_options['primary_cur'], $curs)) {
            $options = array(
                array(
                    'type' => 'text',
                    'slug' => 'nm_merchant_id',
                    'title' => __('Кошелек в системе NixMoney'),
                    'notice' => __('Кошелек, зарегистрированный в системе "NIXMONEY". Узнать его можно в аккаунте Nixmoney')
                ),
                array(
                    'type' => 'text',
                    'slug' => 'nm_merchant_name',
                    'title' => __('Имя магазина'),
                    'notice' => __('Название магазина которое будет видимо при совершении платежа')
                ),
                array(
                    'type' => 'text',
                    'slug' => 'nm_secret_key',
                    'title' => __('Секретный ключ'),
                    'notice' => __('Должен совпадать с секретным паролем, указанным для аккаунта Nixmoney')
                ),
                array(
                    'type' => 'select',
                    'slug' => 'nm_work_mode',
                    'title' => __('Секретный ключ'),
                    'values' => array(
                        'Тестовый',
                        'Рабочий'
                    )
                )
            );
        }else{
            $options = array(
                array(
                    'type' => 'custom',
                    'slug' => 'notice',
                    'notice' => __('<span style="color:red">Данное подключение не поддерживает действующую валюту сайта.<br>'
                        . 'Поддерживается работа с USD и EUR</span>')
                )
            );
        }

        $child .= $opt->child(
            array(
                'name'=>'connect_sale',
                'value'=>$this->form_pay_id
            ),
            array(
                $opt->options_box( __('Настройки подключения NixMoney'), $options)
            )
        );

        return $child;
    }

    function pay_form($data){
        global $rmag_options;

        $work_mode = $rmag_options['nm_work_mode'];
        $merchant_id = $rmag_options['nm_merchant_id'];
        $merchant_name = $rmag_options['nm_merchant_name'];

        $currency = $rmag_options['primary_cur'];

        $baggage_data = ($data->baggage_data)? $data->baggage_data: false;

        $action_url = ($work_mode)? 'https://www.nixmoney.com/merchant.jsp': 'https://dev.nixmoney.com/merchant.jsp';

        $fields = array(
            'PAYEE_ACCOUNT'=>$merchant_id,
            'PAYEE_NAME'=>$merchant_name,
            'PAYMENT_ID'=>$data->pay_id,
            'PAYMENT_AMOUNT'=>$data->pay_summ,
            'PAYMENT_UNITS'=>$currency,
            'STATUS_URL'=>get_permalink($rmag_options['page_result_pay']),
            'PAYMENT_URL'=>get_permalink($rmag_options['page_success_pay']),
            'NOPAYMENT_URL'=>get_permalink($rmag_options['page_fail_pay']),
            'NM_TYPE_PAY'=>$data->pay_type,
            'NM_USER_ID'=>$data->user_id,
            'NM_PAYMENT_ID'=>$data->pay_id,
            'NM_BAGGAGE_DATA'=>$baggage_data,
            'BAGGAGE_FIELDS'=>"NM_TYPE_PAY NM_USER_ID NM_PAYMENT_ID NM_BAGGAGE_DATA"
        );

        $form = parent::form($fields,$data,$action_url);

        return $form;
    }

    function result($data){
        global $rmag_options;

        $secret_key = $rmag_options['nm_secret_key'];

        if ( !isset($_POST['V2_HASH']) ) return false;

        $string=
         $_POST['PAYMENT_ID'].':'.$_POST['PAYEE_ACCOUNT'].':'.$_POST['PAYMENT_AMOUNT'].':'.$_POST['PAYMENT_UNITS'].':'.
         $_POST['PAYMENT_BATCH_NUM'].':'.$_POST['PAYER_ACCOUNT'].':'.strtoupper(md5($secret_key)).':'.$_POST['TIMESTAMPGMT'];

        $v2key = $_POST['V2_HASH'];
        $sign_hash = strtoupper(md5($string));

        if($v2key != $sign_hash){
            rcl_mail_payment_error($sign_hash);
            exit ($_POST['PAYMENT_ID'] . '|error');
        }

        $data->pay_summ = $_REQUEST['PAYMENT_AMOUNT'];
        $data->pay_id = $_REQUEST['PAYMENT_ID'];
        $data->user_id = $_REQUEST['NM_USER_ID'];
        $data->pay_type = $_REQUEST['NM_TYPE_PAY'];
        $data->baggage_data = $_REQUEST['NM_BAGGAGE_DATA'];

        if(!parent::get_pay($data)){
            parent::insert_pay($data);
            exit ($_POST['PAYMENT_ID'] . '|success');
        }
    }

    function success(){
        global $rmag_options;

        $data['pay_id'] = $_REQUEST['NM_PAYMENT_ID'];
        $data['user_id'] = $_REQUEST['NM_USER_ID'];

        if(parent::get_pay((object)$data)){
            wp_redirect(get_permalink($rmag_options['page_successfully_pay'])); exit;
        } else {
            wp_die('Платеж не найден в базе данных');
        }

    }

}

}
