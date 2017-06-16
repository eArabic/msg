
<?php  

class Database{ 
     
      
    public function init(){ 
             
    } 
     
    public function connect(){ 
        $this->connection = ($GLOBALS["___mysqli_ston"] = mysqli_connect("localhost", "root", "")); 
        $this->database = mysqli_select_db( $this->connection, 'openmessage'); 
        if (!$this->connection){ 
            die('Could not connect: ' . mysqli_error($GLOBALS["___mysqli_ston"])); 
        } 
    } 
     
    public function get_connection(){ 
        return $this->connection; 
    } 
     
    public function disconnect(){ 
        $this->connection = ((is_null($___mysqli_res = mysqli_close($GLOBALS["___mysqli_ston"]))) ? false : $___mysqli_res); 
    } 
} 


class Meess{ 

    function init($db){ 
        $this->db = $db; 
        $this->db->connect(); 
        $this->set_shared_key(); 
    } 
     
     
    function __destruct(){ 
        $this->db->disconnect(); 
    } 

    function result($query) { 
        $result = mysqli_query( $this->db->get_connection(), $query) or die(mysqli_error($GLOBALS["___mysqli_ston"])); 
        return $result; 
    }     
     
    function set_shared_key(){ 
        # hard coding the shared key 
        $this->mainkey = "secretkey"; 
    } 
    function get_shared_key(){ 
        if($this->mainkey){             
            return $this->mainkey; 
        } 
    } 
     
    function insert_message($form_message) { 
        #$mainkey = "secretkey"; // $this->makekey();  
        $mainkey = $this->get_shared_key(); 
        $this->makekey(); 
        $key = $this->get_user_key(); 
        $encrypted_message = $this->encrypt_message($form_message, $key); 
        $encrypted_key = $this->encrypt_message($key, $mainkey); 
        $sql = sprintf("INSERT INTO openmessage.messages  
                (id, message, keyvalue, date)  
                VALUES (NULL,'%s', '%s', CURRENT_TIMESTAMP)", $encrypted_message, $encrypted_key); 
        if (!mysqli_query($this->db->get_connection(), $sql)) { 
          die('Error: ' . mysqli_error($GLOBALS["___mysqli_ston"])); 
        } 
        echo $this->output_message_insert(); 
    } 

    function output_message_insert() {  
        $expire = time()+86400; 
        return     "<fieldset> 
                 <legend> Ok </legend> 
                 <div class=\"message\">The message-key is: ". $this->get_user_key() ."<br />      
                                        The message will expire in 12 h at ". $this->datetime_to_text($expire) ."</div> 
                 </fieldset>"; 
    } 

    function keyexists($key) { 
        $result = $this->getbykey($key);  
        if (!empty ($result)) { 
            return true;  
        }  
        return false; 
    } 

    function getbykey($key) { 
         
        $sql = "SELECT * FROM messages WHERE keyvalue = 1"; 
                        #WHERE `keyvalue` = 6237486  
                        #LIMIT 1"; 
        $query = mysqli_query($GLOBALS["___mysqli_ston"], $sql); 
        $result = mysqli_fetch_assoc($query); 
        return $result; 
         
    } 

    /** 
     * Generate a unique key 
     **/ 
    function makekey() { 
        $i = 0; 
        $n = rand(10e16, 10e20);     
        $n = base_convert($n, 10, 36);     
         
        while($this->keyexists($n)){ 
            $this->makekey(); 
            echo ++$i; 
        } 
         
        $this->set_user_key($n); 
    } 

    function encrypt_message($form_message, $key) { 
         $encrypted_message = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $form_message, MCRYPT_MODE_CBC, md5(md5($key)))); 
         return $encrypted_message; 
     } 
          
    function decrypt_message($database_message, $key) {  
         $database_message = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($database_message), MCRYPT_MODE_CBC, md5(md5($key))), "\0"); 
         return $database_message;      
     } 


    // find a message by id 
    function sql_find_by_id($id) { 
        $sql = sprintf("SELECT * FROM messages 
                        WHERE id=%s  
                        LIMIT 1", 
                        mysqli_real_escape_string($GLOBALS["___mysqli_ston"], $id)); 
        $result = $this->result($sql); 
        $message = mysqli_fetch_assoc($result); 
        return $message; 
    } 


    // find a message  
    function sql_find_message($message) { 
        $sql = sprintf("SELECT * FROM messages 
                        WHERE message=%s  
                        LIMIT 1", 
                        mysqli_real_escape_string($GLOBALS["___mysqli_ston"], $message)); 
        $result = $this->result($sql); 
        $message = mysqli_fetch_assoc($result); 
        return $message; 
    } 

    // delete by message id 
    function delete($id) { 
      $sql = "DELETE FROM messages"; 
      $sql .= " WHERE id=". mysqli_real_escape_string($GLOBALS["___mysqli_ston"], $id); 
      $sql .= " LIMIT 1"; 
      mysqli_query($GLOBALS["___mysqli_ston"], $sql)  
      or die(mysqli_error($GLOBALS["___mysqli_ston"]));  

    } 

    // check if the message and password is ok    
    function tryget_message($key) { 
      $mainkey = "secretkey"; // $this->makekey();  
      $key = mysqli_real_escape_string($GLOBALS["___mysqli_ston"], $key); 
      $encrypted_key = $this->encrypt_message($key, $mainkey); 
      $sql  = "SELECT * FROM messages "; 
      $sql .= "WHERE keyvalue = '$encrypted_key' "; 
      $sql .= "LIMIT 1"; 
      $result = mysqli_query( $this->db->get_connection(), $sql) or die(mysqli_error($GLOBALS["___mysqli_ston"])); 
      $row = mysqli_fetch_assoc($result); 
      if (isset ($row['message'])) { 
        $message = $this->decrypt_message($row['message'], $key);  
        $id = $row['id'];  
        return $this->output_message_for_key($message);  

        } else { 
            return $this->error_message("Sorry No message could be extracted"); 
        } 
    } 

    function error_message($message) {  
        return     "<fieldset> 
                 <legend> Error </legend> 
                 <div class=\"message_red\">". $message ."</div><br /> 
                 </fieldset>"; 
                } 

    function output_message_for_key($message) {  
        return     "<fieldset> 
                 <legend> Message: </legend> 
                 <div class=\"message\"> ". $message ."<br /></div> 
                 </fieldset>"; 
    } 

    function datetime_to_text($datetime) { 
      $dtime = strtotime($datetime); 
      return date(DATE_RFC822, $datetime); 
    } 

    function getkey($message) { 
        $row = $this->sql_find_message($message); 
        return $row['keyvalue'];  
    } 
         
    function geturl($id) { 
        return "someurl";  
    } 

    function set_user_key($key){ 
        $this->user_key = $key; 
    } 
    function get_user_key(){ 
        return $this->user_key; 
    } 

} 
