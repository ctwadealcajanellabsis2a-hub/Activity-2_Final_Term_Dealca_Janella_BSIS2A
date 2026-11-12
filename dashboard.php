<?php
session_start();
require_once 'db.php';

// 1. ACCESS CONTROL: Kick out anyone who is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$message = '';

// 2. USER MANAGEMENT ACTIONS (Admin Only)
if ($role === 'admin' && isset($_GET['user_action']) && isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    
    // Prevent admin from deleting or locking themselves out
    if ($target_id !== (int)$_SESSION['user_id']) {
        if ($_GET['user_action'] === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
            $stmt->execute([$target_id]);
            $message = "User account approved.";
        } elseif ($_GET['user_action'] === 'reject') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$target_id]);
            $message = "User account rejected.";
        } elseif ($_GET['user_action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $message = "User account deleted.";
        }
    }
}

// 3. PRODUCT CRUD OPERATIONS (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crud_action = $_POST['crud_action'] ?? '';

    // CREATE
    if ($crud_action === 'create') {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];

        $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $category, $price, $stock]);
        $message = "Product added successfully.";
    }

    // UPDATE
    if ($crud_action === 'update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];

        $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, stock = ? WHERE id = ?");
        $stmt->execute([$name, $category, $price, $stock, $id]);
        $message = "Product updated successfully.";
    }
}

// DELETE PRODUCT
if (isset($_GET['delete_product'])) {
    $prod_id = (int)$_GET['delete_product'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$prod_id]);
    $message = "Product removed from inventory.";
}

// READ: Fetch Product to Edit (if requested)
$edit_product = null;
if (isset($_GET['edit_product'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_product']]);
    $edit_product = $stmt->fetch();
}

// READ: Fetch All Products
$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

// READ: Fetch All Users (Admin Only)
$users = [];
if ($role === 'admin') {
    $users = $pdo->query("SELECT id, username, role, status FROM users ORDER BY id DESC")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coffee Shop Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f1ea; color: #3e2723; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d7ccc8; padding-bottom: 15px; margin-bottom: 20px; }
        .badge { background: #6d4c41; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 0.85em; text-transform: uppercase; }
        .alert { background: #dcedc8; color: #33691e; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; }
        h2 { border-left: 4px solid #6d4c41; padding-left: 10px; margin-top: 30px; }
        form.crud-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; background: #efebe9; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        input, select, button { padding: 8px 10px; border: 1px solid #bcaaa4; border-radius: 4px; }
        button { background: #5d4037; color: white; cursor: pointer; border: none; font-weight: bold; }
        button:hover { background: #3e2723; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d7ccc8; padding: 10px; text-align: left; }
        th { background: #5d4037; color: white; }
        .btn { padding: 5px 8px; text-decoration: none; border-radius: 3px; font-size: 0.85em; color: white; display: inline-block; }
        .btn-approve { background: #2e7d32; }
        .btn-reject { background: #f57c00; }
        .btn-delete { background: #c62828; }
        .btn-edit { background: #1565c0; }
        .logout { background: #c62828; padding: 8px 14px; text-decoration: none; color: white; border-radius: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1 style="margin:0;">Coffee Shop Management</h1>
            <p style="margin:5px 0 0;">Welcome, <strong><?= htmlspecialchars($username) ?></strong> <span class="badge"><?= htmlspecialchars($role) ?></span></p>
        </div>
        <a href="logout.php" class="logout">Logout</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- INVENTORY CRUD SECTION (Accessible to both Admin & Staff) -->
    <h2><?= $edit_product ? 'Edit Product' : 'Add New Product' ?></h2>
    <form method="POST" class="crud-form">
        <input type="hidden" name="crud_action" value="<?= $edit_product ? 'update' : 'create' ?>">
        <?php if ($edit_product): ?>
            <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">
        <?php endif; ?>
        
        <input type="text" name="name" placeholder="Item Name (e.g., Iced Latte)" value="<?= htmlspecialchars($edit_product['name'] ?? '') ?>" required>
        <select name="category" required>
            <option value="Hot Coffee" <?= ($edit_product['category'] ?? '') === 'Hot Coffee' ? 'selected' : '' ?>>Hot Coffee</option>
            <option value="Iced Coffee" <?= ($edit_product['category'] ?? '') === 'Iced Coffee' ? 'selected' : '' ?>>Iced Coffee</option>
            <option value="Pastries" <?= ($edit_product['category'] ?? '') === 'Pastries' ? 'selected' : '' ?>>Pastries</option>
            <option value="Supplies" <?= ($edit_product['category'] ?? '') === 'Supplies' ? 'selected' : '' ?>>Supplies</option>
        </select>
        <input type="number" step="0.01" name="price" placeholder="Price (₱)" value="<?= htmlspecialchars($edit_product['price'] ?? '') ?>" required>
        <input type="number" name="stock" placeholder="Stock Qty" value="<?= htmlspecialchars($edit_product['stock'] ?? '') ?>" required>
        
        <button type="submit"><?= $edit_product ? 'Update Item' : 'Add Item' ?></button>
        <?php if ($edit_product): ?>
            <a href="dashboard.php" class="btn btn-reject" style="text-align:center; padding-top:9px;">Cancel</a>
        <?php endif; ?>
    </form>

    <h2>Inventory & Menu List</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td>₱<?= number_format($p['price'], 2) ?></td>
                <td><?= $p['stock'] ?></td>
                <td>
                    <a href="dashboard.php?edit_product=<?= $p['id'] ?>" class="btn btn-edit">Edit</a>
                    <a href="dashboard.php?delete_product=<?= $p['id'] ?>" class="btn btn-delete" onclick="return confirm('Delete this item?');">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <tr><td colspan="6" style="text-align:center;">No products in inventory yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- USER MANAGEMENT SECTION (Visible ONLY to Admin) -->
    <?php if ($role === 'admin'): ?>
        <h2>User Management (Admin Access Only)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Manage Account</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td><strong><?= strtoupper(htmlspecialchars($u['status'])) ?></strong></td>
                    <td>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="dashboard.php?user_action=approve&id=<?= $u['id'] ?>" class="btn btn-approve">Approve</a>
                            <a href="dashboard.php?user_action=reject&id=<?= $u['id'] ?>" class="btn btn-reject">Reject</a>
                            <a href="dashboard.php?user_action=delete&id=<?= $u['id'] ?>" class="btn btn-delete" onclick="return confirm('Delete user account?');">Delete</a>
                        <?php else: ?>
                            <em>Current Session</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
You sent
padding: 8px 14px; text-decoration: none; color: white; border-radius: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1 style="margin:0;">Coffee Shop Management</h1>
            <p style="margin:5px 0 0;">Welcome, <strong><?= htmlspecialchars($username) ?></strong> <span class="badge"><?= htmlspecialchars($role) ?></span></p>
        </div>
        <a href="logout.php" class="logout">Logout</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- INVENTORY CRUD SECTION (Accessible to both Admin & Staff) -->
    <h2><?= $edit_product ? 'Edit Product' : 'Add New Product' ?></h2>
    <form method="POST" class="crud-form">
        <input type="hidden" name="crud_action" value="<?= $edit_product ? 'update' : 'create' ?>">
        <?php if ($edit_product): ?>
            <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">
        <?php endif; ?>
        
        <input type="text" name="name" placeholder="Item Name (e.g., Iced Latte)" value="<?= htmlspecialchars($edit_product['name'] ?? '') ?>" required>
        <select name="category" required>
            <option value="Hot Coffee" <?= ($edit_product['category'] ?? '') === 'Hot Coffee' ? 'selected' : '' ?>>Hot Coffee</option>
            <option value="Iced Coffee" <?= ($edit_product['category'] ?? '') === 'Iced Coffee' ? 'selected' : '' ?>>Iced Coffee</option>
            <option value="Pastries" <?= ($edit_product['category'] ?? '') === 'Pastries' ? 'selected' : '' ?>>Pastries</option>
            <option value="Supplies" <?= ($edit_product['category'] ?? '') === 'Supplies' ? 'selected' : '' ?>>Supplies</option>
        </select>
        <input type="number" step="0.01" name="price" placeholder="Price (₱)" value="<?= htmlspecialchars($edit_product['price'] ?? '') ?>" required>
        <input type="number" name="stock" placeholder="Stock Qty" value="<?= htmlspecialchars($edit_product['stock'] ?? '') ?>" required>
        
        <button type="submit"><?= $edit_product ? 'Update Item' : 'Add Item' ?></button>
        <?php if ($edit_product): ?>
            <a href="dashboard.php" class="btn btn-reject" style="text-align:center; padding-top:9px;">Cancel</a>
        <?php endif; ?>
    </form>

    <h2>Inventory & Menu List</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td>₱<?= number_format($p['price'], 2) ?></td>
                <td><?= $p['stock'] ?></td>
                <td>
                    <a href="dashboard.php?edit_product=<?= $p['id'] ?>" class="btn btn-edit">Edit</a>
                    <a href="dashboard.php?delete_product=<?= $p['id'] ?>" class="btn btn-delete" onclick="return confirm('Delete this item?');">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <tr><td colspan="6" style="text-align:center;">No products in inventory yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- USER MANAGEMENT SECTION (Visible ONLY to Admin) -->
    <?php if ($role === 'admin'): ?>
        <h2>User Management (Admin Access Only)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Manage Account</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td><strong><?= strtoupper(htmlspecialchars($u['status'])) ?></strong></td>
                    <td>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="dashboard.php?user_action=approve&id=<?= $u['id'] ?>" class="btn btn-approve">Approve</a>
                            <a href="dashboard.php?user_action=reject&id=<?= $u['id'] ?>" class="btn btn-reject">Reject</a>
                            <a href="dashboard.php?user_action=delete&id=<?= $u['id'] ?>" class="btn btn-delete" onclick="return confirm('Delete user account?');">Delete</a>
                        <?php else: ?>
                            <em>Current Session</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>