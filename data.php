<?php
$dataFile = 'data.json';
header('Content-Type: text/html; charset=utf-8');

// 处理POST数据上报
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleDataReport();
    exit;
}

// 处理GET数据查询
showDataPage();

/******************** 功能函数 ********************/
function handleDataReport() {
    global $dataFile;
    
    try {
        // 验证JSON格式
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("无效的JSON格式");
        }

        // 验证必要字段
        $required = ['qq', 'spins'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("缺少必要字段: $field");
            }
        }

        if (!preg_match('/^\d{5,12}$/', $data['qq'])) {
            throw new Exception("QQ号格式无效");
        }

        if (!is_numeric($data['spins']) || $data['spins'] < 0) {
            throw new Exception("旋转次数必须为非负数");
        }

        // 读写数据
        $dataset = readData();
        $timestamp = date('Y-m-d H:i:s');
        
        $found = false;
        foreach ($dataset as &$item) {
            if ($item['qq'] == $data['qq']) {
                // 修改这里：将赋值改为累加
                $item['spins'] += (int)$data['spins'];  // <-- 主要修改点
                $item['timestamp'] = $timestamp;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $dataset[] = [
                'qq' => $data['qq'],
                'spins' => (int)$data['spins'],
                'timestamp' => $timestamp
            ];
        }

        // 写入文件
        file_put_contents($dataFile, json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

function showDataPage() {
    global $dataFile;
    $searchQQ = $_GET['qq'] ?? '';
    $dataset = readData();

    // 过滤数据
    if (!empty($searchQQ)) {
        $dataset = array_filter($dataset, function($item) use ($searchQQ) {
            return strpos($item['qq'], $searchQQ) !== false;
        });
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>QQ旋转数据查询系统</title>
    <style>
        .container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .search-box { margin-bottom: 30px; text-align: center; }
        input[type="text"] { 
            padding: 10px; 
            width: 300px; 
            border: 2px solid #00a1d6;
            border-radius: 25px;
            font-size: 16px;
        }
        button {
            padding: 10px 25px;
            background: #00a1d6;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            background: #008cba;
            transform: scale(1.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .no-data { 
            text-align: center; 
            color: #666;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="search-box">
            <form method="GET">
                <input type="text" 
                       name="qq" 
                       placeholder="输入QQ号进行搜索" 
                       value="<?= htmlspecialchars($searchQQ) ?>"
                       pattern="\d{5,12}"
                       title="请输入5-12位数字">
                <button type="submit">立即搜索</button>
            </form>
        </div>

        <?php if (!empty($dataset)): ?>
        <table>
            <thead>
                <tr>
                    <th>QQ号</th>
                    <th>旋转次数</th>
                    <th>最后更新时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dataset as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['qq']) ?></td>
                    <td><?= number_format($item['spins']) ?></td>
                    <td><?= htmlspecialchars($item['timestamp']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">当前没有找到匹配的数据记录</div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
}

function readData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        file_put_contents($dataFile, '[]');
        return [];
    }
    
    $content = file_get_contents($dataFile);
    $data = json_decode($content, true);
    
    // 数据有效性验证
    if (!is_array($data)) return [];
    
    return array_filter($data, function($item) {
        return isset($item['qq'], $item['spins'], $item['timestamp']);
    });
}



