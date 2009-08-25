<?php
	
	class extension_interspire extends Extension {
		private $params = array();
		
		public function about() {
			return array(
				'name'			=> 'Filter: Interspire Email Marketer',
				'version'		=> '1.0',
				'release-date'	=> '2009-08-25',
				'author'		=> array(
					'name'			=> 'Brendan Abbott',
					'website'		=> 'http://www.bloodbone.ws/',
					'email'			=> 'brendan@bloodbone.ws'
				),
				'description'	=> 'Add a user to a the Interspire Email Marketer subscribers list.'
	 		);
		}
		
		public function uninstall() {
			$this->_Parent->Configuration->remove('interspire');
			$this->_Parent->saveConfig();
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilterDocumentation',
					'callback'	=> 'appendDocumentation'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilterDocumentation',
					'callback'	=> 'appendDocumentation'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'preProcessData'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'postProcessData'
				)
			);
		}
		
		public function get($value) {
			return $this->_Parent->Configuration->get($value, 'interspire');
		}
		
		public function appendDocumentation($context) {
			if (!in_array('interspire', $context['selected'])) return;
			
			$context['documentation'][] = new XMLElement('h3', 'Interspire Email Marketer Filter');
			
			$context['documentation'][] = new XMLElement('p', '
				To use the Interspire Email Marketer filter, add the following field to your form:
			');
			
			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode('
<input name="interspire[list]" value="$interspire-list-id" type="hidden" />
<input name="interspire[field][Email]" value="$field-email-address" type="hidden" />
<input name="interspire[field][$interspire-custom-field-id]" value="Value for field Custom..." type="hidden" />
			');
		}
		
		public function appendFilter($context) {
			$context['options'][] = array(
				'interspire',
				@in_array(
					'interspire', $context['selected']
				),
				'Interspire'
			);
		}
		
		public function appendPreferences($context) {
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(
				new XMLElement('legend', 'Interspire Email Marketer Filter')
			);
			
			$path = Widget::Label('XML Path');
			$path->appendChild(Widget::Input(
				'settings[interspire][path]', General::Sanitize($this->get('path'))
			));
			$group->appendChild($path);
			
			$username = Widget::Label('Username');
			$username->appendChild(Widget::Input(
				'settings[interspire][username]', General::Sanitize($this->get('username'))
			));
			$group->appendChild($username);
			
			$token = Widget::Label('User Token');
			$token->appendChild(Widget::Input(
				'settings[interspire][token]', General::Sanitize($this->get('token'))
			));
			$group->appendChild($token);
			
			$context['wrapper']->appendChild($group);			
		}
		
		public function manipulateParameters($context) {
			$context['params']['interspire'] = $this->getHash();
		}
		
		public function parseFields($values) {
			foreach($values as $key => $value) {
				if (is_array($value)) {
					$values[$key] = $this->parseFields($value);
					
				} else {
					$values[$key] = preg_replace_callback(
						'/\$([a-z][a-z0-9\-]*)/i',
						array($this, 'parseFieldValues'), trim($value)
					);
				}
			}
			
			return $values;
		}
		
		public function parseFieldValues($matches) {
			$param = $matches[1];
			$value = @$this->params[$param];
			$output = '';
			
			if (is_array($value)) {
				foreach($value as $key => $line) {
					if (is_string($key)) {
						$key .= ': ';
					} else {
						$key = (string)++$key . '. ';
					}
					
					$output .= General::CRLF . $key . $line;
				}
				
			} else {
				$output = (string)$value;
			}
			
			return $output;
		}
		
		public function preProcessData($context) {
			$message = null; $valid = true;
			
			if (!in_array('interspire', $context['event']->eParamFILTERS)) return;
			
			if (
				!isset($_POST['interspire']['list'])
				or !isset($_POST['interspire']['field']['Email'])
			) {
				$message = 'Required field missing, see event documentation.';
				$valid = false;
			}
			
			$context['messages'][] = array('interspire', $valid, $message);
		}
		
		public function prepareFields($path, $fields) {
			$output = array();
			
			foreach($fields as $key => $value) {
				$key = "{$path}-{$key}";
				
				if (is_array($value)) {
					$temp = $this->prepareFields($key, $value);
					$output = array_merge($output, $temp);
					
				} else {
					$output[$key] = $value;
				}
			}
			
			return $output;
		}

		private function doRequest($request) {						
			$ch = curl_init($this->get('path'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
			$result = @curl_exec($ch);
			
			if($result === false) {
				return "<response>
							<status>ERROR</status>
							<errormessage>Error performing request</errormessage>
						</response>";
			}
			else {
				$xml_doc = simplexml_load_string($result);				
				return $xml_doc;				
			}
		}
		
		
		public function validateToken() {
			// Start Request Data
			$xml = new XMLElement('xmlrequest');			
				$xml->appendChild(
					new XMLElement("username", $this->get('username'))
				);				
				$xml->appendChild(
					new XMLElement("usertoken", $this->get('token'))
				);
				$xml->appendChild(
					new XMLElement("requesttype", 'authentication')
				);
				$xml->appendChild(
					new XMLElement("requestmethod", 'xmlapitest')
				);
				
			// Add default details
			$details = new XMLElement('details');			
			$xml->appendChild($details);
			
			$result = $this->doRequest($xml->generate());
			
			if($result->status == 'SUCCESS') {
				return true;
			} else {
				return false;
			}
		}
		
		public function postProcessData($context) {
			if (!in_array('interspire', $context['event']->eParamFILTERS)) return;			
			//if (!$this->validateToken()) return;
			
			// Create params:
			$this->params = $this->prepareFields('field', $_POST['fields']);
			
			// Parse values:
			$values = $this->parseFields($_POST['interspire']['field']);			
			
			// Start Request Data
			$xml = new XMLElement('xmlrequest');			
				$xml->appendChild(
					new XMLElement("username", $this->get('username'))
				);				
				$xml->appendChild(
					new XMLElement("usertoken", $this->get('token'))
				);
				$xml->appendChild(
					new XMLElement("requesttype", 'subscribers')
				);
				$xml->appendChild(
					new XMLElement("requestmethod", 'AddSubscriberToList')
				);
				
			// Add default details
			$details = new XMLElement('details');
				$details->appendChild(
					new XMLElement("format", 'html')
				);
				$details->appendChild(
					new XMLElement("confirmed", 'yes')
				);				
				
				$custom = new XMLElement('customfields');
				
				// Add custom details		
				foreach ($values as $name => $value) {
					if ($name == 'Email') {
						$details->appendChild(
							new XMLElement("emailaddress", $value)
						);									
					} else {
						$item = new XMLElement("item");						
						$item->appendChild(
							new XMLElement('fieldid', $name)
						);
						$item->appendChild(
							new XMLElement('value', $value)
						);
						
						$custom->appendChild($item);
					}
				}
				
				// Append
				$details->appendChild($custom);
				$xml->appendChild($details);
			
			header('content-type: text/plain');	
			echo $xml->generate(true,4,true);
			
			die();	

			$result = $this->doRequest($xml->generate());

			if($result->status == 'SUCCESS') {
				return true;
			} else {
				return false;
			}			
		}
	}
	
?>