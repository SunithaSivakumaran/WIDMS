<?php
declare(strict_types=1);

function renderContactLensOverview(PDO $db): void
{
    $stock = $db->query("SELECT lu.power, SUM(CASE WHEN lu.status='available' THEN 1 ELSE 0 END) in_stock, s.company_name, MAX(DATE(lu.created_at)) last_received FROM contact_lens_units lu JOIN contact_lens_bulk_orders bo ON bo.id=lu.bulk_order_id JOIN suppliers s ON s.id=bo.supplier_id GROUP BY lu.power,s.id,s.company_name ORDER BY lu.power")->fetchAll();
    $orders = $db->query("SELECT ar.id,b.full_name,b.nic,ds.name division_name,ar.prescribed_power requested_power,(SELECT assigned.power FROM contact_lens_units assigned WHERE assigned.aid_request_id=ar.id AND assigned.status<>'returned-to-vendor' ORDER BY assigned.id DESC LIMIT 1) current_power,ar.status,ar.created_at,(SELECT COUNT(*) FROM contact_lens_units available WHERE available.power=ar.prescribed_power AND available.status='available') exact_stock FROM aid_requests ar JOIN beneficiaries b ON b.id=ar.beneficiary_id JOIN ds_divisions ds ON ds.id=b.ds_division_id JOIN inventory_items i ON i.id=ar.item_id JOIN item_categories c ON c.id=i.category_id WHERE LOWER(CONCAT(COALESCE(i.item_name,''),' ',COALESCE(i.variety,''),' ',COALESCE(c.name,''))) LIKE '%contact%lens%' ORDER BY ar.id DESC")->fetchAll();
    ?>
    <section class="admin-data-card lens-overview-card">
      <div class="admin-data-header">
        <h2>
          <span class="lens-blue-dot"></span>
          Contact Lens Stock &mdash; By Power
        </h2>
      </div>
      <div class="admin-data-table-wrap">
        <table class="admin-data-table lens-stock-overview">
          <thead>
            <tr>
              <th>Power</th>
              <th>In Stock</th>
              <th>Company</th>
              <th>Last Received</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>

              <?php if (!$stock): ?>
              <tr>
                <td colspan="5" class="admin-empty-row">No contact lens stock records available.</td>
              </tr>

              <?php else: 
                foreach ($stock as $row): $inStock=(int)$row['in_stock']; ?>
              <tr>
                <td>
                  <strong><?=sprintf('%+.2f',(float)$row['power'])?></strong>
                </td>
                <td class="stock-quantity<?=$inStock===0?' empty':''?>"><?=$inStock?></td>
                <td><?=htmlspecialchars($row['company_name'],ENT_QUOTES,'UTF-8')?></td>
                <td><?=date('d M Y',strtotime($row['last_received']))?></td>
                <td>
                  <span class="stock-pill <?=$inStock>0?'available':'out'?>"><?=$inStock>0?'Available':'Out of Stock'?></span>
                </td>
              </tr>
              <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
    </section>

    <section class="admin-data-card lens-overview-card">
      <div class="admin-data-header">
        <h2>
          <span class="lens-blue-dot"></span>
          All Contact Lens Orders
        </h2>
        <label class="lens-order-search">
          <span aria-hidden="true">&#128269;</span>
          <input id="lens-order-search" type="search" placeholder="Search name, NIC or power..." aria-label="Search contact lens orders">
        </label>
      </div>
      <div class="admin-data-table-wrap">
        <table class="admin-data-table lens-orders-overview" id="lens-overview-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Beneficiary</th>
              <th>NIC</th>
              <th>Division</th>
              <th>Requested Power</th>
              <th>Current Power</th>
              <th>Power Changed?</th>
              <th>Stock Check</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$orders): ?>
            <tr>
              <td colspan="11" class="admin-empty-row">No contact lens orders available.</td>
            </tr>
          <?php else: 
          foreach ($orders as $row): $changed=$row['current_power']!==null&&(float)$row['current_power']!==(float)$row['requested_power']; ?>
          <tr class="lens-overview-row">
            <td>
              CL-<?=str_pad((string)$row['id'],3,'0',STR_PAD_LEFT)?>
            </td>
            <td>
              <strong><?=htmlspecialchars($row['full_name'],ENT_QUOTES,'UTF-8')?></strong>
            </td>
            <td>
              <?=htmlspecialchars($row['nic']?:'—',ENT_QUOTES,'UTF-8')?>
            </td>
            <td>
              <?=htmlspecialchars($row['division_name'],ENT_QUOTES,'UTF-8')?>
            </td>
            <td>
              <strong>
                <?=sprintf('%+.2f',(float)$row['requested_power'])?>
              </strong>
            </td>
            <td>
              <strong>
                <?=$row['current_power']!==null?sprintf('%+.2f',(float)$row['current_power']):'—'?>
              </strong>
            </td>
            <td>
              <span class="power-change-pill <?=$changed?'changed':'same'?>"><?=$changed?'Changed':'Same'?>
              </span>
            </td>
            <td>
              <span class="stock-check-pill <?=(int)$row['exact_stock']>0?'available':'unchecked'?>">
              <?=(int)$row['exact_stock']>0?'Available':'Not checked'?>
              </span>
            </td>
            <td>
              <span class="request-status-pill status-<?=htmlspecialchars($row['status'],ENT_QUOTES,'UTF-8')?>">
              <?=ucwords(str_replace('-',' ',$row['status']))?>
              </span>
            </td>
            <td>
              <?=date('d M Y',strtotime($row['created_at']))?>
            </td>
            <td>
              <?php if(hasRole('admin')&&$row['status']==='pending'):?>
                <form method="post" class="lens-review-form">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>">
                    <input type="hidden" name="action" value="review-request">
                    <input type="hidden" name="request_id" value="<?=(int)$row['id']?>">
                    <input name="reason" placeholder="Rejection reason">
                    <span>
                      <button name="decision" value="approved" class="approve-button">
                        Approve
                      </button>
                      <button name="decision" value="rejected" class="reject-button">
                        Reject
                      </button>
                    </span>
                </form>
              <?php else:?>
                <a class="lens-view-action" href="dashboard.php?page=aid-requests&amp;request_id=<?=(int)$row['id']?>">
                    View
                </a>
              <?php endif;?>

            </td>
          </tr>
          <?php endforeach; 
                endif; ?>
      </tbody>
    </table>
  </div>
    </section>
    <script>document.getElementById('lens-order-search')?.addEventListener('input',event=>{
      const term=event.target.value.trim().toLowerCase();
      document.querySelectorAll('#lens-overview-table .lens-overview-row').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(term));
      });</script>
    <?php
}
