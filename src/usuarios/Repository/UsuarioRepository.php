<?php
namespace App\Usuarios\Repository;

use PDO;
use Exception;

class UsuarioRepository
{
    private PDO $db;

    private array $queries;

    public function __construct(PDO $db)
    {
        $this->db = $db;

        $this->queries = require __DIR__ . '/../Queries/sentencesUsuarios.php';
    }

    // Buscar todos los usuarios con su rol correspondiente
    public function getAll(): array
    {
        $sql = $this->queries['getAll'];
        
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    // Verificar si un correo electrónico ya está registrado
    public function existsEmail(string $email): bool
    {
        $sql = $this->queries['existsEmail'];
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return (bool) $stmt->fetch();
    }

    // Registrar un nuevo usuario aplicando hash a la contraseña
    public function create(array $data): bool
    {
        $sql = $this->queries['create'];
        
        $stmt = $this->db->prepare($sql);

        $response = $stmt->execute([
            ':nombre'   => $data['nombre'],
            ':apellido' => $data['apellido'],
            ':email'    => $data['email'],
            ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
        ]);

        return (bool) $response;

    }

    // Eliminar un usuario por ID
    public function delete(int $id): array
    {
        $sql = $this->queries['delete'];

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
        
        
            if ($stmt->rowCount() > 0) {
                return [
                    'status' => true,
                    'msg'    => 'Usuario eliminado físicamente del sistema con éxito.'
                ];
            }
        
            return [
                'status' => false,
                'msg'    => 'El usuario no pudo ser eliminado porque no existe en el sistema.'
            ];

        } catch (\PDOException $e) {
            // Captura específica del error de clave foránea (Restricción por integridad)
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1451) {
                return [
                    'status' => false,
                    'msg'    => 'No se puede eliminar el usuario porque tiene historial de registros.'
                ];
            }
        
            // Si es cualquier otro error crítico de SQL, devolvemos un mensaje genérico por seguridad
            return [
                'status' => false,
                'msg'    => 'Error interno en la base de datos al intentar procesar la solicitud.'
            ];
        }
    
    }

    public function updateStatus(int $id, int $status): bool
    {
        $sql = $this->queries['updateStatus'];
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':id'     => $id,
            ':status' => $status
        ]);
    }

    
    // Guardar un token de acceso ligero en personal_access_tokens
    public function storeToken(int $usuarioId, string $tokenName, string $tokenPlain): bool
    {
        $sql = $this->queries['storeToken'];
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':nombre'     => $tokenName,
            ':token'      => hash('sha256', $tokenPlain),
            ':expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ]);
    }

    /**
     * Actualizar los datos de perfil y rol de un usuario.
     * @return array{status: bool, msg: string}
     */
    public function update(int $id, array $data): array
    {
        $sql = $this->queries['update'];

        try {
            $stmt = $this->db->prepare($sql);
            
            $stmt->execute([
                ':id'        => $id,
                ':nombre'    => $data['nombre'],
                ':apellido'  => $data['apellido'],
                ':role_id'   => !empty($data['role_id']) ? (int)$data['role_id'] : NULL,
                ':status'   => !empty($data['status']) ? (int)$data['status'] : 0
            ]);

            // Verificamos si realmente se modificó algo o si el ID existía
            if ($stmt->rowCount() > 0) {
                return [
                    'status' => true,
                    'msg'    => 'Datos del usuario actualizados con éxito.'
                ];
            }

            return [
                'status' => true, 
                'msg'    => 'No se realizaron cambios en el usuario (los datos enviados eran idénticos).'
            ];

        } catch (\PDOException $e) {
            
            // Captura si el admin intenta asignar un role_id que NO existe en la tabla roles (Error 1452)
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1452) {
                return [
                    'status' => false,
                    'msg'    => 'El rol seleccionado no es válido o no existe.'
                ];
            }

            return [
                'status' => false,
                'msg'    => 'Error interno en la base de datos al intentar actualizar el usuario.'
            ];
        }
    }

    // =========================================================================
    // MÉTODOS PARA AUTENTICACIÓN Y MANEJO DE TOKENS (PAT)
    // =========================================================================

    /**
     * Buscar un usuario por su correo electrónico para el proceso de Login
     */
    public function getAuthData(string $email): ?array
    {
        $sql = $this->queries['getAuthData'];
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        $usuario = $stmt->fetch();
        
        
        return $usuario ?: null;
    }

    /**
     * Eliminar (revocar) un token específico cuando el usuario cierra sesión
     */
    public function deleteToken(string $hashedToken): bool
    {
        $sql = $this->queries['deleteToken'];
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':token' => $hashedToken
        ]);
    }

    /**
     * Eliminar todos los tokens de un usuario (Cerrar sesión en todos los dispositivos)
     */
    public function deleteAllTokens(int $usuarioId): bool
    {
        $sql = $this->queries['deleteAllTokens'];
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':usuario_id' => $usuarioId
        ]);
    }

    /**
    * Guardar el token temporal para la verificación de correo electrónico
    */
    public function storeVerificationToken(string $email, string $token): bool
    {
        $sql = $this->queries['storeVerificationToken'];
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':email'      => $email,
            ':token'      => hash('sha256', $token),
            ':expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ]);
    }

    /**
     * Obtener el token de verificación activo de un usuario
     */
    public function getVerificationToken(string $email): ?array
    {
        $sql = $this->queries['getVerificationToken'];
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    /**
     * Marcar el correo de un usuario como verificado y activarlo en el sistema
     */
    public function verifyUserEmail(string $email): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Actualizar el usuario (Verificar email y activar estado)
            $sqlUser = $this->queries['verifyEmail']; // UPDATE usuarios SET email_verified_at = CURRENT_TIMESTAMP, status = 1 WHERE email = :email
            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->execute([':email' => $email]);

            // 2. Limpiar el token usado de la tabla password_resets para que no se use dos veces
            $sqlDelete = $this->queries['deleteVerificationToken'];
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->execute([':email' => $email]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Registrar una solicitud de restablecimiento de contraseña
     */
    public function storePasswordResetToken(string $email, string $token): bool
    {
        // 1. Limpiamos cualquier token de recuperación viejo que tuviera este email
        $sqlDelete = $this->queries['deletePasswordReset'];
        $this->db->prepare($sqlDelete)->execute([':email' => $email]);

        // 2. Insertamos el nuevo token con una expiración de 2 horas
        $sqlInsert = $this->queries['storePasswordReset'];
        $stmt = $this->db->prepare($sqlInsert);
        
        return $stmt->execute([
            ':email'      => $email,
            ':token'      => hash('sha256', $token), // Almacenamos el hash por seguridad
            ':expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours'))
        ]);
    }

    /**
    * Buscar un usuario completo mediante su dirección de correo electrónico
    */
    public function findByEmail(string $email): ?array
    {
        $sql = $this->queries['findByEmail'];
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    public function resetPasswordTransaction(string $email, string $hashedPassword): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Actualizar la contraseña
            $sqlUp = $this->queries['updatePassword'];
            $stmtUp = $this->db->prepare($sqlUp);
            $stmtUp->execute([':password' => $hashedPassword, ':email' => $email]);

            // 2. 🔒 Ahora sí funciona findByEmail perfectamente
            $usuario = $this->findByEmail($email);
            if ($usuario) {
                // Cierra todas las sesiones activas usando el ID encontrado
                $this->deleteAllTokens((int)$usuario['id']);
            }

            // 3. Limpiar token de recuperación usado
            $sqlDel = $this->queries['deletePasswordReset'];
            $this->db->prepare($sqlDel)->execute([':email' => $email]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    /**
    * Obtener los datos de sesión de un token de acceso activo
    */
    public function getAccessToken(string $tokenHashed): ?array
    {
        $sql = $this->queries['getAccessToken'];
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $tokenHashed]);
        
        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    public function obtenerDatosGraficoSemanal(): array
    {
        try {
            $stmt = $this->db->prepare($this->queries['getGraficoSemanal']);
            $stmt->execute();

            // Mapeamos los resultados para asegurar tipos nativos correctos (int) en el JSON
            return array_map(function ($row) {
                return [
                    'fecha'            => $row['fecha'],
                    'palmas_marcadas'  => (int) $row['palmas_marcadas'],
                    'palmas_revisadas' => (int) $row['palmas_revisadas']
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (Exception $e) {
            throw new Exception("Error al obtener datos del gráfico semanal: " . $e->getMessage());
        }
    }

};