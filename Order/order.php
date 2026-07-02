<?php
require_once "config/db.php";
if (!isset($_SESSION["user_id"]) && !isset($_SESSION["order_authenticated"])) { header("Location: order_gate.php"); exit; }
include "includes/header.php";

$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll();
?>

<div id="customPromptModal" class="prompt-modal">
    <div class="prompt-box">
        <div class="prompt-title" id="customPromptTitle">Confirm Order Submission</div>
        <div class="prompt-desc" id="customPromptMessage">Send this active selection to kitchen tickets?</div>
        <div class="prompt-actions">
            <button class="prompt-btn p-btn-confirm" id="promptConfirmBtn">Yes, Send</button>
            <button class="prompt-btn p-btn-cancel" onclick="document.getElementById('customPromptModal').style.display='none'">Cancel</button>
        </div>
    </div>
</div>

<div class="nav-bar">
    <a href="javascript:void(0)" class="nav-link active" onclick="scrollToSection('all', this)">All Items</a>
    <?php foreach($categories as $cat): ?>
        <a href="javascript:void(0)" class="nav-link" onclick="scrollToSection('cat_<?= $cat['id'] ?>', this)">
            <?= htmlspecialchars($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="app-body" style="padding-bottom: 120px;">
    <div style="margin-bottom: 15px; max-width: 400px;">
        <input type="text" id="rowSearch" placeholder="Search item or category name..." style="margin: 0;" oninput="searchMenu()">
    </div>
    
    <div id="menuCatalogView"></div>
</div>

<div class="footer-bar">
    <div class="cart-preview" id="cartPreviewText">No active elements chosen in order cart.</div>
    <div class="footer-row">
        <div class="summary-info">Total Bill Due: <span id="cartGrandTotal">₹0</span></div>
        <button type="button" class="btn-submit" onclick="triggerOrderSubmitConfirm()">Submit Order</button>
    </div>
</div>

<script>
let cart = [], menuData = [], categoriesData = <?php echo json_encode($categories); ?>;

function loadCatalog() {
    fetch("api/search_menu.php").then(res => res.json()).then(data => {
        menuData = data;
        renderCategorizedMenu(menuData);
    });
}

function renderCategorizedMenu(items) {
    const mainView = document.getElementById("menuCatalogView");
    mainView.innerHTML = "";

    categoriesData.forEach(cat => {
        const catItems = items.filter(x => x.category_id == cat.id);
        if(catItems.length === 0) return;

        let sectionHtml = `
            <div class="category-section" id="cat_${cat.id}">
                <h3 class="category-title">${cat.name}</h3>
                <div class="grid">
        `;

        catItems.forEach(item => {
            let mediaDisplay = item.image_path ? `<img src="${item.image_path}">` : `<span class="qty-btn" style="border:none; background:none; font-size:1.5rem; margin:0 auto 6px auto;">🍽️</span>`;
            
            sectionHtml += `
                <div class="card menu-row-item" data-name="${item.name.toLowerCase()}">
                    <div class="card-left-group">
                        ${mediaDisplay}
                        <div class="card-details">
                            <div class="card-name">${item.name}</div>
                            <div class="card-price">₹${parseFloat(item.price).toFixed(0)}</div>
                        </div>
                    </div>
                    <div class="qty-controls">
                        <button type="button" class="qty-btn" onclick="modifyCartQty(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, -1)">-</button>
                        <span id="row_qty_${item.id}" class="qty-val">0</span>
                        <button type="button" class="qty-btn" onclick="modifyCartQty(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, 1)">+</button>
                    </div>
                </div>
            `;
        });

        sectionHtml += `</div></div>`;
        mainView.innerHTML += sectionHtml;
    });
    updateQtyCounters();
}

function modifyCartQty(id, name, price, delta) {
    let existing = cart.find(x => x.id === id);
    if(existing) {
        existing.qty += delta;
        if(existing.qty <= 0) cart = cart.filter(x => x.id !== id);
    } else if(delta > 0) {
        cart.push({ id, name, price, qty: 1 });
    }
    updateQtyCounters();
    renderCart();
}

function updateQtyCounters() {
    menuData.forEach(item => {
        const el = document.getElementById(`row_qty_${item.id}`);
        if(el) {
            let inCart = cart.find(x => x.id === item.id);
            el.innerText = inCart ? inCart.qty : 0;
        }
    });
}

function renderCart() {
    const totalEl = document.getElementById("cartGrandTotal");
    const previewEl = document.getElementById("cartPreviewText");
    let grandTotal = 0;

    if(cart.length === 0) {
        previewEl.innerText = "No active elements chosen in order cart.";
        totalEl.innerText = "₹" + grandTotal;
        return;
    }

    let summaryNames = cart.map(x => `${x.qty}x ${x.name}`).join(", ");
    previewEl.innerText = "Items: " + summaryNames;

    cart.forEach(item => { grandTotal += (item.price * item.qty); });
    totalEl.innerText = "Template_Currency_Symbol" + grandTotal;
}

function triggerOrderSubmitConfirm() {
    if(cart.length === 0) return alert("Select menu choices first.");
    document.getElementById("customPromptModal").style.display = "flex";
    document.getElementById("promptConfirmBtn").onclick = function() {
        fetch("api/process_order.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ items: cart })
        }).then(res => res.json()).then(d => {
            if(d.success) { cart = []; updateQtyCounters(); renderCart(); document.getElementById("customPromptModal").style.display = "none"; }
        });
    };
}

function scrollToSection(id, tab) {
    document.querySelectorAll(".nav-link").forEach(t => t.classList.remove("active"));
    tab.classList.add("active");
    if(id === 'all') window.scrollTo({ top: 0, behavior: 'smooth' });
    else { const target = document.getElementById(id); if(target) target.scrollIntoView({ behavior: 'smooth' }); }
}

function searchMenu() {
    let q = document.getElementById("rowSearch").value.toLowerCase();
    document.querySelectorAll(".menu-row-item").forEach(row => {
        row.style.setProperty("display", row.getAttribute("data-name").includes(q) ? (window.innerWidth <= 600 ? "flex" : "block") : "none", "important");
    });
}

window.onload = loadCatalog;
</script>
<?php include "includes/footer.php"; ?>