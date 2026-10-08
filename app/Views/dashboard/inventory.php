<?php $pageTitle = 'Inventory and Supplies – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Inventory and Supplies</p>
        <p class="page-subtitle mb-0">Track equipment, supplies, stock levels, and restock needs.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Add inventory item</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('inventory/save') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Item name</label>
                    <input type="text" name="item" class="form-control" placeholder="Resistance band" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Category</label>
                    <input type="text" name="category" class="form-control" placeholder="Equipment" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Stock</label>
                    <input type="number" name="stock" class="form-control" placeholder="20" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Reorder point</label>
                    <input type="number" name="reorder" class="form-control" placeholder="10">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="Healthy">Healthy</option>
                        <option value="Low">Low</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Add item</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Total stock</span>
                    <i class="bi bi-box text-primary"></i>
                </div>
                <h4 class="mb-0"><?= esc($totalStock) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Low stock</span>
                    <i class="bi bi-exclamation-triangle text-warning"></i>
                </div>
                <h4 class="mb-0"><?= esc($lowStock) ?> items</h4>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Restock needed</span>
                    <i class="bi bi-arrow-repeat text-info"></i>
                </div>
                <h4 class="mb-0"><?= esc($restockNeeded) ?> orders</h4>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Current inventory</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Reorder point</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= esc($item['item']) ?></td>
                            <td><?= esc($item['category']) ?></td>
                            <td><?= esc((string) ($item['stock'] ?? 0)) ?></td>
                            <td><?= esc((string) ($item['reorder'] ?? 0)) ?></td>
                            <td>
                                <?php if (($item['status'] ?? '') === 'Low'): ?>
                                    <span class="badge bg-warning-subtle text-warning">Low</span>
                                <?php elseif (($item['status'] ?? '') === 'Critical'): ?>
                                    <span class="badge bg-danger-subtle text-danger">Critical</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">Healthy</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
