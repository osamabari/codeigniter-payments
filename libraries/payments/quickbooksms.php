<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class QuickBooksMS
{		
	/**
	 * The API method currently being utilized
	*/
	private $_api_method;		

	/**
	 * The API method currently being utilized
	*/
	protected $_api_endpoint;	

	/**
	 * An array for storing all settings
	*/	
	private $_settings = array();

	/**
	 * An array for storing all request data
	*/	
	private $_request = array();	

	/**
	 * The final string to be sent in the http query
	*/	
	protected $_http_query;	
	
	/**
	 * The default params for this api
	*/	
	private	$_default_params;
	
	/**
	 * Constructor method
	*/		
	public function __construct($payments)
	{
		$this->payments = $payments;				
		$this->_default_params = $this->payments->ci->config->item('method_params');
		$this->_api_endpoint = $this->payments->ci->config->item('api_endpoint');	
		$this->_api_settings = array(
			'login'			=> $this->payments->ci->config->item('api_application_login'),
			'connection_ticket'	=> $this->payments->ci->config->item('api_connection_ticket'),
			'xml_version'	=> '1.0',
			'encoding'		=> 'utf-8',
			'xml_extra'		=> $this->payments->ci->config->item('api_qbxml')
		);
	}

	/**
	 * Builds a request
	 * @param	array	array of params
	 * @param	string	the api call to use
	 * @param	string	the type of transaction
	 * @return	array	Array of transaction settings
	*/	
	protected function _build_request($params, $transaction_type = NULL)
	{
		$nodes = array();
		
		$nodes['SignonMsgsRq'] = array(
			'SignonDesktopRq' => array(
				'ClientDateTime' => gmdate('c'),
				'ApplicationLogin' => $this->_api_settings['login'],
				'ConnectionTicket' => $this->_api_settings['connection_ticket']
			)
		);
		
		$nodes['QBMSXMLMsgsRq'] = array();
		
		$method_params = array();
		
		$method_params['TransRequestID'] = mt_rand(1, 1000000); //This is used to avoid duplicate transactions coming from the merchant.
		
		if(isset($params['identifier']))
		{
			$method_params['CreditCardTransID'] = $params['identifier'];
		}
		
		if(isset($params['cc_number']) AND isset($params['cc_exp']))
		{
			$method_params['CreditCardNumber'] = $params['cc_number'];
			
			$month = substr($params['cc_exp'], 0, 2);
			$year = substr($params['cc_exp'], -4, 4);
			
			if(!empty($month) AND !empty($year))
			{
				$method_params['ExpirationMonth'] = $month;
				$method_params['ExpirationYear'] = $year;
			}
		}	
		
		if($transaction_type !== 'recurring' AND $this->_api_method !== 'CustomerCreditCardCaptureRq' AND $this->_api_method !== 'CustomerCreditCardTxnVoidRq' AND $this->_api_method !== 'CustomerCreditCardTxnVoidOrRefundRq')
		{
			$method_params['IsCardPresent'] = FALSE;
		}
		
		if($transaction_type === 'recurring')
		{
			$method_params['IsRecurring'] = TRUE;
		}
				
		if(isset($params['amt']))
		{
			$method_params['Amount'] = $params['amt'];
		}	
		
		if(isset($params['first_name']) AND isset($params['last_name']))
		{
			$method_params['NameOnCard'] = trim($params['first_name']. ' ' .$params['last_name']);
		}
		
		if(isset($params['street']))
		{
			$method_params['CreditCardAddress'] = $params['street'];
		}
		
		if(isset($params['postal_code']))
		{
			$method_params['CreditCardPostalCode'] = $params['postal_code'];
		}
		
		if(isset($params['tax_amt']))
		{
			$method_params['SalesTaxAmt'] = $params['tax_amt'];
		}
		
		if(isset($params['cc_code']))
		{
			$method_params['CardSecurityCode'] = $params['cc_code'];
		}
		
		$nodes['QBMSXMLMsgsRq'][$this->_api_method] = $method_params;		
								
		$request = $this->payments->build_xml_request(
			$this->_api_settings['xml_version'],
			$this->_api_settings['encoding'],
			$nodes,					
			'QBMSXML',
			null,
			$this->_api_settings['xml_extra']
		);
		
		return $request;	
	}
			
	/**
	 * Make a oneoff payment
	 * @param	array	An array of payment params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public function quickbooksms_oneoff_payment($params)
	{
		$this->_api_method = 'CustomerCreditCardChargeRq';
		$this->_request = $this->_build_request($params);			
		return $this->_handle_query();
	}

	/**
	 * Authorize a oneoff payment
	 * @param	array	An array of payment params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public function quickbooksms_authorize_payment($params)
	{
		$this->_api_method = 'CustomerCreditCardAuthRq';
		$this->_request = $this->_build_request($params);			
		return $this->_handle_query();
	}

	/**
	 * Capture a oneoff payment
	 * @param	array	An array of payment params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public function quickbooksms_capture_payment($params)
	{
		$this->_api_method = 'CustomerCreditCardCaptureRq';
		$this->_request = $this->_build_request($params);			
		return $this->_handle_query();
	}

	/**
	 * Void a oneoff payment
	 * @param	array	An array of params, sent from your controller / library
	 * @return	object	The response from the payment gateway
	*/	
	public function quickbooksms_void_payment($params)
	{
		$this->_api_method = 'CustomerCreditCardTxnVoidRq';
		$this->_request = $this->_build_request($params);			
		return $this->_handle_query();	
	}
	
	/**
	 * Refund a transaction
	 * @param	array	An array that contains your identifier
	 * @return	object	The response from the payment gateway
	 *
	*/	
	public function quickbooksms_refund_payment($params)
	{
		$this->_api_method = 'CustomerCreditCardTxnVoidOrRefundRq';
		$this->_request = $this->_build_request($params);		
		return $this->_handle_query();	
	}	
		
	/**
	 * Create a new recurring payment
	 *
	 * @param	array
	 * @return	object
	 *
	 */		
	public function quickbooksms_recurring_payment($params)
	{
		$this->_api_method = 'CustomerCreditCardChargeRq';
		$this->_request = $this->_build_request($params, 'recurring');			
		return $this->_handle_query();
	}				

	/**
	 * Build the query for the response and call the request function
	 *
	 * @param	array
	 * @param	array
	 * @param	string
	 * @return	array
	 */		
	private function _handle_query()
	{	
		$this->_http_query = $this->_request;
		
		include_once 'quickbooksms/request.php';
		include_once 'quickbooksms/response.php';
		
		$request = QuickBooksMS_Request::make_request();
		//var_dump($request);exit;
		$response_object = $this->payments->parse_xml($request);
		$response = QuickBooksMS_Response::parse_response($response_object);
		
		return $response;
	}		
		
}