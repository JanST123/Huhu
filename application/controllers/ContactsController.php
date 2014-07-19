<?php
/**
 * Contains the ContactsController class
 */

/**
 * Class ContactsController
 *
 * Contains all actions which manages contacts
 * (search, invite, add, remove(...) contancts)
 */
class ContactsController extends \Huhu\Library\Controller\Action
{

  /**
   * @var array Contains the valiated user input
   */
  private $_validatedInput;

  /**
   * Inits the controller
   * @throws \Huhu\Library\Auth\Exception
   */
  public function init()
  {
    /* Initialize action controller here */
    $this->_helper->viewRenderer->setRender('json');

    \Huhu\Library\Auth::auth();

    $this->_validateInput();

    parent::init();
  }

  /**
   * Returns the contactlist
   */
  public function getAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');


    $contacts = \Huhu\Library\Contact::getContactList($currentUser['id']);
    $requests = \Huhu\Library\Contact::getOpenRequests($currentUser['id']);
    if (!is_array($contacts)) {
      $contacts = Array();
    }
    if (!is_array($requests)) {
      $requests = Array();
    }


    $this->view->dataJson = Array(
      'success' => true,
      'contacts' => array_merge($contacts, $requests),
    );
  }

  /**
   * Returns the contacts not on invisible list
   */
  public function getVisibleAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');

    $contacts = \Huhu\Library\Contact::getContactList($currentUser['id']);
    $invisibleContacts = \Huhu\Library\User::getInvisibleForList();

    foreach ($invisibleContacts as $ic) {
      foreach ($contacts as $ck => $c) {
        if ($c['id'] == $ic['id']) {
          unset($contacts[$ck]);
        }
      }
    }

    if (!is_array($contacts)) {
      $contacts = Array();
    }


    $this->view->dataJson = Array(
      'success' => true,
      'contacts' => array_values($contacts),
    );
  }


  /**
   * Returns the contacts on invisible list
   */
  public function getInvisibleAction()
  {
    $invisibleContacts = \Huhu\Library\User::getInvisibleForList();


    if (!is_array($invisibleContacts)) {
      $invisibleContacts = Array();
    }


    $this->view->dataJson = Array(
      'success' => true,
      'contacts' => array_values($invisibleContacts),
    );
  }


  /**
   * Returns invitable contacts for a chat (most like getAction, but if a chatid is given filter out contacts which are in the chat)
   */
  public function getInvitableAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');

    $contacts = \Huhu\Library\Contact::getContactList($currentUser['id'], true);

    if (!is_array($contacts)) {
      $contacts = Array();
    }

    if (isset($this->_validatedInput['chatid']) && $this->_validatedInput['chatid']) {
      // filter out contacts which already are in the chat
      $chat = \Huhu\Library\Chat::reopen($this->_validatedInput['chatid']);
      if (isset($chat['users']) && is_array($chat['users'])) {
        foreach ($contacts as $k => $contact) {
          if (array_key_exists($contact['id'], $chat['users'])) {
            // already in chat, remove
            unset($contacts[$k]);
          }
        }
      }
    }


    $this->view->dataJson = Array(
      'success' => true,
      'contacts' => array_values($contacts),
    );

  }


  /**
   * adds a contact to the own contact list
   */
  public function addAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');
    $db = Zend_Registry::get('Zend_Db');


    if (!empty($this->_validatedInput['userid'])) {
      $contactUser = null;
      $res = $db->query("SELECT user FROM users WHERE id = " . (int)$this->_validatedInput['userid']);
      if ($res) {
        $contactUser = $res->fetch(Zend_Db::FETCH_ASSOC);
      } else {
        throw new \Huhu\Library\Exception('Invalid contact user');
      }

      if (\Huhu\Library\Contact::addContact($currentUser['id'], $this->_validatedInput['userid'])) {
        \Huhu\Library\Push::push(
          $this->_validatedInput['userid'],
          \Huhu\Library\Pusher::assembleContactRequest(
            $this->translate->_("%s wants to add you to the contact list.", $contactUser['user'])
          )
        );

        $this->view->dataJson = Array(
          'success' => true,
          'message' => sprintf($this->translate->_('The user was added to your contact list, but has to be accepted by %s first.'), $contactUser['user']),
        );
      }
    }
  }

  /**
   * Accepts a contact request
   */
  public function acceptAction()
  {
    if (!empty($this->_validatedInput['id'])) {
      $currentUser = Zend_Registry::get('loggedinuser');
      if (\Huhu\Library\Contact::acceptContact($currentUser['id'], $this->_validatedInput['id'])) {

        \Huhu\Library\Push::push(
          Array($this->_validatedInput['id'], $currentUser['id']),
          \Huhu\Library\Pusher::assembleOpenChat(
            $this->translate->_("%s has accepted your contact request", $currentUser['user'])
          )
        );

        $this->view->dataJson = Array(
          'success' => true,
          'message' => $this->translate->_('You now have each other on your contact list.'),
        );
      }
    }
  }


  /**
   * Rejects a contact request
   */
  public function rejectAction()
  {
    if (!empty($this->_validatedInput['id'])) {
      $currentUser = Zend_Registry::get('loggedinuser');
      if (\Huhu\Library\Contact::rejectContact($currentUser['id'], $this->_validatedInput['id'])) {

        \Huhu\Library\Push::push(
          Array($this->_validatedInput['id'], $currentUser['id']),
          \Huhu\Library\Pusher::assembleOpenChat(null)
        );

        $this->view->dataJson = Array(
          'success' => true,
          'message' => $this->translate->_('The contact request was rejected'),
        );
      }
    }
  }


  /**
   * removes a contact from the own contact list
   */
  public function removeAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');
    if (!empty($this->_validatedInput['id'])) {
      if (\Huhu\Library\Contact::removeContact($currentUser['id'], $this->_validatedInput['id'])) {

        \Huhu\Library\Push::push(
          Array($this->_validatedInput['id'], $currentUser['id']),
          \Huhu\Library\Pusher::assembleOpenChat(null)
        );


        $this->view->dataJson = Array(
          'success' => true,
          'message' => $this->translate->_('The contact was removed from your contact list, you were also removed from the contact list of this contact'),
        );
      }
    }
  }


  /**
   * searches for a contact
   */
  public function searchAction()
  {
    $results = \Huhu\Library\Contact::search($this->_validatedInput['username'], $this->_validatedInput['additional']);
    if (false === $results) {
      $this->view->dataJson = Array(
        'success' => true,
        'foundcount' => 0,
        'message' => $this->translate->_('No search criteria given'),
        'results' => Array(),
      );
    } else {
      $this->view->dataJson = Array(
        'success' => true,
        'foundcount' => count($results),
        'message' => sprintf($this->translate->_('Found %d results'), count($results)),
        'results' => array_values($results),
      );
    }
  }

  /**
   * Receives all the data from the phones adressbooks and looks for matching users in our db...
   */
  public function syncWithPhoneAdressbookAction()
  {
    $contacts = json_decode(html_entity_decode($this->_validatedInput['contacts']));

    $results = Array();

    if (is_array($contacts)) {
      foreach ($contacts as $contact) {
        $name = $contact->displayName;
        if (empty($name) && isset($contact->name)) {
          $name = $contact->name->givenName . ' ' . $contact->name->familyName;
        }

        $emails = Array();
        if (isset($contact->emails) && is_array($contact->emails)) {
          foreach ($contact->emails as $email) {
            $emails[] = $email->value;
          }
        }

        $numbers = Array();
        $mobiles = Array();
        if (isset($contact->phoneNumbers) && is_array($contact->phoneNumbers)) {
          foreach ($contact->phoneNumbers as $number) {

            $numberValue = preg_replace('/[^0-9\+]/', '', $number->value);
            // normalize number
            if (strpos($numberValue, '00') === 0) {
              $numberValueNormalized = '+' . substr($numberValue, 2);
            } else {
              if (strpos($numberValue, '+') === 0) {
                $numberValueNormalized = $numberValue;
              } else {
                $numberValueNormalized = '+49' . $numberValue;
              }
            }


            if ($number->type == 'mobile') {
              $mobiles[] = $numberValueNormalized;
            } else {
              $numbers[] = $numberValueNormalized;
            }
          }
        }

        $cities = Array();
        $zips = Array();
        if (isset($contact->addresses) && is_array($contact->addresses)) {
          foreach ($contact->addresses as $address) {
            $cities[] = $address->locality;
            $zips[] = $address->postalCode;
          }
        }

        $organizations = Array();
        if (isset($contact->organizations) && is_array($contact->organizations)) {
          foreach ($contact->organizations as $organization) {
            if (isset($organization->name)) {
              $organizations[] = $organization->name;
            }
          }
        }


        $age = null;
        if (!empty($contact->birthday)) {
          $expl = explode('-', $contact->birthday);
          $yb = $expl[0];
          $mb = $expl[1];
          $db = $expl[2];


          $age = date('Y') - $yb;
          if ($mb > date('m')) {
            $age--;
          } elseif ($mb == date('m')) {
            if ($db > date('d')) {
              $age--;
            }
          }
        }

        $urls = Array();
        if (isset($contact->urls) && is_array($contact->urls)) {
          foreach ($contact->urls as $url) {
            $urls[] = $url->value;
          }
        }


        // do the searching...
        $additional = Array();
        if (count($emails)) {
          foreach ($emails as $i => $email) {
            $additional['email' . str_pad('', $i, '+')] = $email;
          }
        }

        if (count($cities)) {
          foreach ($cities as $i => $city) {
            $additional['city' . str_pad('', $i, '+')] = $city;
          }
        }

        if (count($zips)) {
          foreach ($zips as $i => $zip) {
            $additional['zip' . str_pad('', $i, '+')] = $zip;
          }
        }

        if ($age) {
          $additional['age'] = $age;
        }

        if (!empty($contact->birthday)) {
          $additional['birthday'] = $contact->birthday;
        }

        $name = explode(' ', trim($name));
        foreach ($name as $i => $n) {
          if ($i < (count($name) - 1)) {
            $additional['firstname' . str_pad('', $i, '+')] = $n;
          } else {
            $additional['lastname'] = $n;
          }
        }

        if (count($organizations)) {
          foreach ($organizations as $i => $organization) {
            $additional['company' . str_pad('', $i, '+')] = $organization;
          }
        }


        if (count($numbers)) {
          foreach ($numbers as $i => $number) {
            $additional['phone' . str_pad('', $i, '+')] = $number;
          }
        }

        if (count($mobiles)) {
          foreach ($mobiles as $i => $number) {
            $additional['mobile' . str_pad('', $i, '+')] = $number;
          }
        }

        if (count($urls)) {
          foreach ($urls as $i => $url) {
            $additional['url' . str_pad('', $i, '+')] = $url;
          }
        }


        $resultsNew = \Huhu\Library\Contact::search((!empty($contact->nickname) ? $contact->nickname : ''), $additional, true);
        if (is_array($resultsNew)) {
          foreach ($resultsNew as $r) {
            $exists = false;
            foreach ($results as $r2) {
              if ($r2['name'] == $r['name']) {
                $exists = true;
                break;
              }
            }
            if (!$exists) {
              $results[] = $r;
            }
          }
        }
      }
    }

    $this->view->dataJson = Array(
      'success' => true,
      'foundcount' => count($results),
      'message' => sprintf($this->translate->_('Found %d results'), count($results)),
      'results' => array_values($results),
    );
  }


  /**
   * Validates user input (request variables)
   */
  private function _validateInput()
  {
    $filters = Array('*' => Array('StringTrim', 'StripTags'));
    $validators = Array();
    $input = new Zend_Filter_Input($filters, $validators, $this->_getAllParams());


    $this->_validatedInput = Array(
      'additional' => (isset($_REQUEST['additional']) ? $_REQUEST['additional'] : null),
      // will be filtered few lines below
      'contacts' => $input->contacts,
      'id' => (int)$input->id,
      'chatid' => (int)$input->chatid,
      'userid' => (int)$input->userid,
      'username' => $input->username,
    );

    if (is_array($this->_validatedInput['additional'])) {
      $filterChain = new Zend_Filter();
      $filterChain->addFilter(new Zend_Filter_StringTrim())
        ->addFilter(new Zend_Filter_StripTags());

      array_walk_recursive(
        $this->_validatedInput['additional'],
        function (&$val, $index, $filterChain) {
          $val = $filterChain->filter($val);
        },
        $filterChain
      );
    } else {
      $this->_validatedInput['additional'] = null;
    }
  }
}

