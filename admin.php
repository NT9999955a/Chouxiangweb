<?php
session_start();
$dataFile = 'data.json';
$adminPassword = 'admin123'; // 强烈建议生产环境修改密码

// 登录验证
if (!isset($_SESSION['loggedin'])) {
    if (isset($_POST['password'])) {
        if ($_POST['password'] === $adminPassword) {
            $_SESSION['loggedin'] = true;
            header('Location: admin.php');
            exit;
        } else {
            die(showErrorPage('密码错误'));
        }
    } else {
        showLoginForm();
        exit;
    }
}

// 处理注销
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// 主逻辑处理
handleAdminRequests();

function handleAdminRequests() {
    global $dataFile;

    $action = $_POST['action'] ?? '';
    $targetQQ = $_POST['qq'] ?? '';
    $message = '';

    try {
        // 读取数据集
        $dataset = json_decode(file_get_contents($dataFile), true) ?? [];
        
        // 处理POST请求
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            switch ($action) {
                case 'delete':
                    // 删除账号
                    $dataset = array_values(array_filter($dataset, function($item) use ($targetQQ) {
                        return $item['qq'] != $targetQQ;
                    }));
                    $message = showMessage('账号已删除！', 'success');
                    break;

                case 'toggle_lock':
                    // 切换锁定状态
                    foreach ($dataset as &$item) {
                        if ($item['qq'] == $targetQQ) {
                            $item['locked'] = !$item['locked'];
                            $status = $item['locked'] ? '锁定' : '解锁';
                            $message = showMessage("账号已{$status}！", 'success');
                            break;
                        }
                    }
                    break;

                case 'update':
                    // 更新数据
                    if (isset($_POST['spins'])) {
                        $found = false;
                        foreach ($dataset as &$item) {
                            if ($item['qq'] == $targetQQ) {
                                if ($item['locked']) {
                                    throw new Exception("账号已锁定，无法修改");
                                }
                                
                                $item['spins'] = max(0, (int)$_POST['spins']);
                                $item['timestamp'] = date('Y-m-d H:i:s');
                                $message = showMessage('数据更新成功！', 'success');
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) throw new Exception("账号不存在");
                    }
                    break;
            }

            // 保存数据
            if (!empty($action)) {
                file_put_contents($dataFile, json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

    } catch (Exception $e) {
        $message = showMessage("操作失败：{$e->getMessage()}", 'error');
    }

    // 显示管理界面
    showAdminInterface($dataset, $message);
}

function showAdminInterface($dataset, $message = '') {
    $searchQQ = $_GET['q'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <title>数据管理系统</title>
        <style>
            /* 样式优化 */
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                background: #f5f5f5;
            }
            .container {
                max-width: 1000px;
                margin: 2rem auto;
                padding: 2rem;
                background: white;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            .search-box {
                margin-bottom: 2rem;
                padding: 1.5rem;
                background: #f8f9fa;
                border-radius: 8px;
            }
            input[type="text"], input[type="number"] {
                padding: 0.8rem;
                border: 2px solid #e0e0e0;
                border-radius: 6px;
                width: 300px;
                transition: border-color 0.3s;
            }
            input:focus {
                border-color: #00a1d6;
                outline: none;
            }
            button {
                padding: 0.8rem 1.5rem;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.3s;
            }
            .primary-btn {
                background: #00a1d6;
                color: white;
            }
            .primary-btn:hover {
                background: #008cba;
            }
            .danger-btn {
                background: #dc3545;
                color: white;
            }
            .status-tag {
                display: inline-block;
                padding: 0.3rem 0.8rem;
                border-radius: 4px;
                font-size: 0.9rem;
            }
            .locked { background: #dc3545; }
            .unlocked { background: #28a745; }
            .alert {
                padding: 1rem;
                margin: 1rem 0;
                border-radius: 6px;
            }
            .success { background: #d4edda; color: #155724; }
            .error { background: #f8d7da; color: #721c24; }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 1.5rem;
            }
            th, td {
                padding: 1rem;
                border-bottom: 1px solid #eee;
            }
            th {
                background: #f8f9fa;
                font-weight: 600;
                text-align: left;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div style="text-align:right; margin-bottom:1rem;">
                <a href="?logout" class="danger-btn">退出登录</a>
            </div>
            
            <h1>数据管理系统</h1>
            <?= $message ?>

            <div class="search-box">
                <form method="GET">
                    <input type="text" 
                           name="q" 
                           placeholder="输入QQ号搜索" 
                           value="<?= htmlspecialchars($searchQQ) ?>"
                           required
                           pattern="\d{5,12}">
                    <button type="submit" class="primary-btn">搜索账号</button>
                </form>
            </div>

            <?php if ($searchQQ): ?>
                <?php
                $results = array_filter($dataset, function($item) use ($searchQQ) {
                    return $item['qq'] === $searchQQ;
                });
                
                if (!empty($results)):
                    $account = reset($results);
                    $isLocked = $account['locked'] ?? false;
                ?>
                <div class="account-panel">
                    <div class="account-header">
                        <h3>账号管理：<?= htmlspecialchars($account['qq']) ?></h3>
                        <span class="status-tag <?= $isLocked ? 'locked' : 'unlocked' ?>">
                            <?= $isLocked ? '已锁定' : '正常' ?>
                        </span>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="qq" value="<?= htmlspecialchars($account['qq']) ?>">

                        <div class="form-group">
                            <label>旋转次数：</label>
                            <input type="number" 
                                   name="spins" 
                                   value="<?= htmlspecialchars($account['spins']) ?>"
                                   min="0"
                                   <?= $isLocked ? 'disabled' : '' ?>
                                   required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" 
                                    name="action" 
                                    value="update"
                                    class="primary-btn"
                                    <?= $isLocked ? 'disabled' : '' ?>>
                                保存修改
                            </button>
                            
                            <button type="submit" 
                                    name="action" 
                                    value="toggle_lock"
                                    class="<?= $isLocked ? 'unlocked' : 'locked' ?>">
                                <?= $isLocked ? '解除锁定' : '锁定账号' ?>
                            </button>
                            
                            <button type="submit" 
                                    name="action" 
                                    value="delete"
                                    class="danger-btn"
                                    onclick="return confirm('确定永久删除该账号？')">
                                删除账号
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                    <div class="alert error">未找到相关账号</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

function showLoginForm() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>管理员登录</title>
        <style>
            .login-container {
                max-width: 400px;
                margin: 100px auto;
                padding: 2rem;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .login-form input {
                width: 100%;
                margin: 0.5rem 0;
                padding: 0.8rem;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>管理员登录</h2>
            <form method="POST" class="login-form">
                <input type="password" 
                       name="password" 
                       placeholder="输入管理员密码" 
                       required>
                <button type="submit" class="primary-btn" style="width:100%;margin-top:1rem;">
                    登录系统
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

function showMessage($text, $type = 'success') {
    return "<div class='alert $type'>$text</div>";
}

function showErrorPage($message) {
    ?>
    <html>
    <body>
        <div style="padding:2rem; color:red; text-align:center;">
            <?= $message ?>
            <p><a href="admin.php">返回登录</a></p>
        </div>
    </body>
    </html>
    <?php
}