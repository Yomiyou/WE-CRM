<?php
/**
 * Created by PhpStorm.
 * User: andreas.martin
 * Date: 08.10.2017
 * Time: 14:39
 */

namespace service;

use domain\Customer;
use domain\Agent;
use domain\AuthToken;
use dao\CustomerDAO;
use dao\AgentDAO;
use dao\AuthTokenDAO;

/**
 * @access public
 * @author andreas.martin
 */
class WECRMServiceImpl implements WECRMService {
    /**
     * @AttributeType WECRMService
     */
    private static $instance = null;
    /**
     * @AttributeType int
     */
    private $currentAgentId;

    /**
     * @access public
     * @return WECRMService
     * @static
     * @ReturnType WECRMService
     */
    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @access protected
     */
    protected function __construct() { }

    /**
     * @access private
     */
    private function __clone() { }

    /**
     * @access protected
     * @return boolean
     * @ReturnType boolean
     */
    protected function verifyAuth() {
        if(isset($this->currentAgentId))
            return true;
        return false;
    }

    /**
     * @access public
     * @param String email
     * @param String password
     * @return boolean
     * @ParamType email String
     * @ParamType password String
     * @ReturnType boolean
     */
    public function verifyAgent($email, $password) {
        $agentDAO = new AgentDAO();
        $agent = $agentDAO->findByEmail($email);
        if (isset($agent)) {
            if (password_verify($password, $agent->getPassword())) {
                if (password_needs_rehash($agent->getPassword(), PASSWORD_DEFAULT)) {
                    $agent->setPassword(password_hash($password, PASSWORD_DEFAULT));
                    $agentDAO->update($agent);
                }
                $this->currentAgentId = $agent->getId();
                return true;
            }
        }
        return false;
    }

    /**
     * @access public
     * @return Agent
     * @ReturnType Agent
     */
    public function readAgent() {
        if($this->verifyAuth()) {
            $agentDAO = new AgentDAO();
            return $agentDAO->read($this->currentAgentId);
        }
        return null;
    }

    /**
     * @access public
     * @param string name
     * @param String email
     * @param String password
     * @return boolean
     * @ParamType name string
     * @ParamType email String
     * @ParamType password String
     * @ReturnType boolean
     */
    public function editAgent($name, $email, $password) {
        $agent = new Agent();
        $agent->setName($name);
        $agent->setEmail($email);
        $agent->setPassword(password_hash($password, PASSWORD_DEFAULT));
        $agentDAO = new AgentDAO();
        if($this->verifyAuth()) {
            $agent->setId($this->currentAgentId);
            if($agentDAO->read($this->currentAgentId)->getEmail() !== $agent->getEmail()) {
                if (!is_null($agentDAO->findByEmail($email))) {
                    return false;
                }
            }
            $agentDAO->update($agent);
            return true;
        }else{
            if(!is_null($agentDAO->findByEmail($email))){
                return false;
            }
            $agentDAO->create($agent);
            return true;
        }
    }

    /**
     * @access public
     * @param Customer customer
     * @return Customer
     * @ParamType customer Customer
     * @ReturnType Customer
     */
    public function createCustomer(Customer $customer) {
        if($this->verifyAuth()) {
            $customerDAO = new CustomerDAO();
            $customer->setAgentId($this->currentAgentId);
            return $customerDAO->create($customer);
        }
        return null;
    }

    /**
     * @access public
     * @param int customerId
     * @return Customer
     * @ParamType customerId int
     * @ReturnType Customer
     */
    public function readCustomer($customerId) {
        if($this->verifyAuth()) {
            $customerDAO = new CustomerDAO();
            return $customerDAO->read($customerId);
        }
        return null;
    }

    /**
     * @access public
     * @param Customer customer
     * @return Customer
     * @ParamType customer Customer
     * @ReturnType Customer
     */
    public function updateCustomer(Customer $customer) {
        if($this->verifyAuth()) {
            $customerDAO = new CustomerDAO();
            return $customerDAO->update($customer);
        }
        return null;
    }

    /**
     * @access public
     * @param int customerId
     * @ParamType customerId int
     */
    public function deleteCustomer($customerId) {
        if($this->verifyAuth()) {
            $customerDAO = new CustomerDAO();
            $customer = new Customer();
            $customer->setId($customerId);
            $customerDAO->delete($customer);
        }
    }

    /**
     * @access public
     * @return Customer[]
     * @ReturnType Customer[]
     */
    public function findAllCustomer() {
        if($this->verifyAuth()){
            $customerDAO = new CustomerDAO();
            return $customerDAO->findByAgent($this->currentAgentId);
        }
        return null;
    }

    /**
     * @access public
     * @param String token
     * @return boolean
     * @ParamType token String
     * @ReturnType boolean
     */
    public function validateToken($token) {
        $tokenArray = explode(":", $token);
        $authTokenDAO = new AuthTokenDAO();
        $authToken = $authTokenDAO->findBySelector($tokenArray[0]);
        if (!empty($authToken)) {
            if(time()<=(new \DateTime($authToken->getExpiration()))->getTimestamp()){
                if (hash_equals(hash('sha384', hex2bin($tokenArray[1])), $authToken->getValidator())) {
                    $this->currentAgentId = $authToken->getAgentid();
                    return true;
                }
            }
            $authTokenDAO->delete($authToken);
        }
        return false;
    }

    /**
     * @access public
     * @param int type
     * @param String email
     * @return String
     * @ParamType type int
     * @ParamType email String
     * @ReturnType String
     *
     * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence
     * https://www.owasp.org/index.php/PHP_Security_Cheat_Sheet#Authentication
     * https://stackoverflow.com/a/31419246
     */
    public function issueToken($type = self::AGENT_TOKEN, $email = null) {
        $token = new AuthToken();
        $token->setSelector(bin2hex(random_bytes(5)));
        $token->setType(self::AGENT_TOKEN);
        $token->setAgentid($this->currentAgentId);
        $timestamp = (new \DateTime('now'))->modify('+30 days');
        $token->setExpiration($timestamp->format("Y-m-d H:i:s"));
        $validator = random_bytes(20);
        $token->setValidator(hash('sha384', $validator));
        $authTokenDAO = new AuthTokenDAO();
        $authTokenDAO->create($token);
        return $token->getSelector() .":". bin2hex($validator);
    }
}