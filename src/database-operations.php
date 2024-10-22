<?php
//basic connection (mysql)
try {
    // Connection parameters
    $host = 'mysql';
    $dbname = 'mydb';
    $username = 'myuser';
    $password = 'mypassword';
    
    // Create connection string
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password);
    
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected successfully (mysql)<br>";
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

//postgre syntax
try {
    $host = 'postgres';
    $port = '5432';
    $dbname = 'mydb';
    $username = 'myuser';
    $password = 'mypassword';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Connected successfully (postgre)<br>";
} catch(PDOException $e) {
    error_log("Connection Error: " . $e->getMessage());
    throw new Exception("Database connection failed");
}

//create db structure
try {
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    if ($stmt->fetchColumn() > 0) {
        // Table exists, alter if required
    } else {
        // Table doesn't exist, create it
        $sql = "CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "Table users created successfully\n";

        // Create the index
        $sql = "CREATE INDEX idx_users_email ON users(email);";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "Index idx_users_email created successfully\n";
    }
} catch(PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo $e->getMessage();
}

class User {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($username, $name, $lastname, $email, $password) {
        try {
            $sql = "INSERT INTO users (username, name, lastname, email, password) 
                    VALUES (:username, :name, :lastname, :email, :password) 
                    RETURNING id, username, email, created_at";
            
            $stmt = $this->pdo->prepare($sql);
            
            // Hash password before storing
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt->execute([
                ':username' => $username,
                ':name' => $name,
                ':lastname' => $lastname,
                ':email' => $email,
                ':password' => $hashedPassword
            ]);
            
            // PostgreSQL allows returning inserted data
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') { // Unique violation in PostgreSQL
                throw new Exception("Username or email already exists");
            }
            error_log("Create user error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getById($id) {
        try {
            $sql = "SELECT id, username, name, lastname, email, password, created_at 
                    FROM users WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $result = $stmt->fetch();
            if (!$result) {
                throw new Exception("User not found");
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getByEmail($email) {
        try {
            $sql = "SELECT id, username, name, lastname, email, created_at 
                    FROM users WHERE LOWER(email) = LOWER(:email)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Get all users with pagination
    public function getAllUsers($limit = 20, $offset = 0) {
        try {
            $sql = "SELECT id, username, name, lastname, email, created_at 
                    FROM users 
                    ORDER BY created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get all users error: " . $e->getMessage());
            throw $e;
        }
    }

    public function update($id, $userData) {
        try {
            $this->pdo->beginTransaction();
            
            $allowedFields = ['username', 'name', 'lastname', 'email'];
            $fields = array_intersect_key($userData, array_flip($allowedFields));
            
            if (empty($fields)) {
                return false;
            }
            
            $sql = "UPDATE users SET ";
            $updates = [];
            $params = [':id' => $id];
            
            foreach ($fields as $field => $value) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $value;
            }
            
            $sql .= implode(', ', $updates);
            $sql .= " WHERE id = :id RETURNING id, username, email";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            $this->pdo->commit();
            
            return $result;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() == '23505') { // Unique violation
                throw new Exception("Username or email already exists");
            }
            error_log("Update user error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function updatePassword($id, $newPassword) {
        try {
            $sql = "UPDATE users 
                    SET password = :password
                    WHERE id = :id 
                    RETURNING id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':password' => password_hash($newPassword, PASSWORD_DEFAULT)
            ]);
            
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Update password error: " . $e->getMessage());
            throw $e;
        }
    }

    public function delete($id) {
        try {
            $this->pdo->beginTransaction();
            
            $sql = "DELETE FROM users WHERE id = :id RETURNING id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $result = $stmt->fetch() !== false;
            $this->pdo->commit();
            
            return $result;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Delete user error: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteByUsername($username) {
        try {
            $this->pdo->beginTransaction();
            
            $sql = "DELETE FROM users WHERE username = :username RETURNING id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':username' => $username]);
            
            $result = $stmt->fetch() !== false;
            $this->pdo->commit();
            
            return $result;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Delete user error: " . $e->getMessage());
            throw $e;
        }
    }

    public function verifyUserPassword($username, $password){
        try{
            $sql = "SELECT password FROM users WHERE username = :username";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':username' => $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return false; // User not found
            }
    
            // Verify password using password_verify()
            return password_verify($password, $row['password']);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Check password error: " . $e->getMessage());
            throw $e;
        }

    }
}

// Usage Example:
$user = new User($pdo);
//delete by username if exists
$user->deleteByUsername('johndoe');

$newUser = $user->create('johndoe', 'John', 'Doe', 'john@example.com', 'secure123');
echo "Created user with ID: " . $newUser['id']."<br>";
echo "<section><p>Created user data</p>";
echo "<pre>";
print_r($newUser);
echo "</pre>";
echo "</section>";

$newPassword = "password123";
$user->updatePassword($newUser['id'], $newPassword);
$newUser = $user->getById($newUser['id']);
echo "<section><p>Update user data</p>";
echo "<pre>";
print_r($newUser);
echo "</pre>";
$isPassOk = $user->verifyUserPassword($newUser['username'], $newPassword);
echo "<p> Is password now ".$newPassword."?: ".($isPassOk ? "true" : "false")."<p>";
echo "</section>";


echo $user->delete($newUser['id']);

?>