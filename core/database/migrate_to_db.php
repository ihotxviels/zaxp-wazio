<?php
/**
 * 🚀 MIGRATION SCRIPT: JSON -> PostgreSQL
 * Wazio Module Sync
 */

require_once __DIR__ . '/../../config.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("❌ Falha na conexão com o banco de dados.");
}

echo "<h1>🚀 Iniciando Migração Wazio...</h1>";

// 1. MIGRAR USUÁRIOS
echo "<h3>1. Migrando Usuários...</h3>";
if (file_exists(USERS_FILE)) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    foreach ($users as $username => $u) {
        try {
            $stmt = $pdo->prepare("INSERT INTO crm_users (username, password_hash, full_name, role, is_active, instance_limits, instances, modulos, hidden_instances) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON CONFLICT (username) DO UPDATE SET 
                                   password_hash = EXCLUDED.password_hash,
                                   full_name = EXCLUDED.full_name,
                                   role = EXCLUDED.role,
                                   is_active = EXCLUDED.is_active,
                                   instance_limits = EXCLUDED.instance_limits,
                                   instances = EXCLUDED.instances,
                                   modulos = EXCLUDED.modulos,
                                   hidden_instances = EXCLUDED.hidden_instances");

            $stmt->execute([
                $username,
                $u['password'] ?? '',
                $u['nome'] ?? $username,
                $u['role'] ?? 'user',
                $u['ativo'] ?? true,
                $u['limite_instancias'] ?? 10,
                json_encode($u['instancias'] ?? []),
                json_encode($u['modulos'] ?? []),
                json_encode($u['hidden_instances'] ?? [])
            ]);
            echo "✅ Usuário <b>$username</b> migrado/atualizado.<br>";
        } catch (Exception $e) {
            echo "❌ Erro ao migrar usuário $username: " . $e->getMessage() . "<br>";
        }
    }
} else {
    echo "⚠️ users.json não encontrado.<br>";
}

// 2. MIGRAR CONFIGURAÇÕES DE CONTINGÊNCIA (CHIPS)
echo "<h3>2. Migrando Configurações de Contingência...</h3>";
$chipsDataDir = __DIR__ . '/../../app/Views/admin/chips_data';
if (is_dir($chipsDataDir)) {
    $files = glob($chipsDataDir . '/config_*.json');
    foreach ($files as $file) {
        $filename = basename($file);
        $username = str_replace(['config_', '.json'], '', $filename);
        $config = json_decode(file_get_contents($file), true);

        if ($config) {
            try {
                $stmt = $pdo->prepare("INSERT INTO crm_settings (username, setting_key, setting_value) 
                                       VALUES (?, ?, ?)
                                       ON CONFLICT (username, setting_key) DO UPDATE SET 
                                       setting_value = EXCLUDED.setting_value,
                                       updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([
                    $username,
                    'contingencia_config',
                    json_encode($config)
                ]);
                echo "✅ Config. Contingência para <b>$username</b> migrada.<br>";
            } catch (Exception $e) {
                echo "❌ Erro ao migrar config de $username: " . $e->getMessage() . "<br>";
            }
        }
    }
} else {
    echo "⚠️ Diretório chips_data não encontrado.<br>";
}

echo "<h2>🌟 Migração Concluída!</h2>";
?>