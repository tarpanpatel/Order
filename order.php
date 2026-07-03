<?php 
session_start();

// Allow access if they are logged in as either Admin or Chef
if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "Admin" && $_SESSION["role"] !== "Chef")) {
    header("Location: login.php");
    exit;
}
require_once "config/db.php"; 

if (!isset($_SESSION["user_id"]) && !isset($_SESSION["order_authenticated"])) { 
    header("Location: login.php"); 
    exit; 
} 
include "includes/header.php"; 
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC")->fetchAll(); 
?>

<div id="customPromptModal" class="prompt-modal">     
    <div class="prompt-box" id="promptBoxInner">         
        <div id="promptInteractiveContent">
            <div class="prompt-title" id="customPromptTitle">Confirm Order Submission</div>         
            <div class="prompt-desc" id="customPromptMessage">Send this active selection to kitchen tickets?</div>         
            <div class="prompt-actions" id="promptActionButtons">             
                <button class="prompt-btn p-btn-confirm" id="promptConfirmBtn">Yes, Send</button>             
                <button class="prompt-btn p-btn-cancel" onclick="document.getElementById('customPromptModal').style.display='none'">Cancel</button>         
            </div>     
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

<style>
.nav-bar {
    position: sticky !important;
    top: 0 !important; 
    z-index: 100 !important;
    background: #ffffff !important;
    padding: 12px 16px !important;
    display: flex !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
    border-bottom: 1px solid #e2e8f0 !important;
    margin-left: -12px;
    margin-right: -12px;
    margin-top: -12px;
    margin-bottom: 15px;
}
.menu-grid-layout {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
    gap: 12px !important;
}
.menu-grid-layout .menu-row-item {
    background: var(--card-bg, #ffffff) !important;
    border-radius: var(--radius, 12px) !important;
    border: 1px solid #e2e8f0 !important;
    padding: 12px !important;
    text-align: center !important;
    position: relative !important;
    display: block !important; 
}
.menu-grid-layout .card-left-group {
    display: block !important;
    text-align: center !important;
}
.menu-grid-layout .card-left-group img,
.menu-grid-layout .menu-item-icon-placeholder {
    width: 44px !important;
    height: 44px !important;
    object-fit: cover !important;
    border-radius: 50% !important;
    margin: 0 auto 6px auto !important;
    display: block !important;
}
.menu-grid-layout .card-name {
    font-size: 13px !important;
    font-weight: 600 !important;
    height: 36px !important;
    overflow: hidden !important;
    margin-bottom: 4px !important;
    color: var(--text-main) !important;
    display: -webkit-box !important;
    -webkit-line-clamp: 2 !important;
    -webkit-box-orient: vertical !important;
}
.menu-grid-layout .card-price {
    color: var(--primary) !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    margin-bottom: 8px !important;
}
.menu-grid-layout .qty-controls {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 10px !important;
    margin-top: 8px !important;
}
@media (max-width: 1023px) {
    .nav-bar {
        top: 0 !important; 
        margin-left: -12px;
        margin-right: -12px;
        margin-top: -12px;
    }
}
@media (max-width: 600px) {
    .menu-grid-layout {
        display: flex !important;
        flex-direction: column !important;
        gap: 6px !important;
    }
    .menu-grid-layout .menu-row-item {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 8px 12px !important;
        border-radius: 8px !important;
        text-align: left !important;
    }
    .menu-grid-layout .card-left-group {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        flex: 1 !important;
        min-width: 0 !important;
        text-align: left !important;
    }
    .menu-grid-layout .card-left-group img,
    .menu-grid-layout .menu-item-icon-placeholder {
        width: 28px !important;
        height: 28px !important;
        margin-bottom: 0 !important;
        border-radius: 4px !important;
        display: inline-block !important;
    }
    .menu-grid-layout .card-details {
        display: flex !important;
        flex-direction: column !important;
        min-width: 0 !important;
    }
    .menu-grid-layout .card-name {
        font-size: 12px !important;
        height: auto !important;
        margin-bottom: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        display: block !important;
    }
    .menu-grid-layout .card-price {
        font-size: 11px !important;
        margin-top: 1px !important;
        margin-bottom: 0 !important;
    }
    .menu-grid-layout .qty-controls {
        margin-left: auto !important; 
        margin-top: 0 !important;
    }
}
</style>

<div class="app-body" style="padding-bottom: 120px;">     
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
            <div class="category-section" id="cat_${cat.id}" style="scroll-margin-top: 140px;">                 
                <h3 class="category-title">${cat.name}</h3>                 
                <div class="menu-grid-layout">`;         
        
        catItems.forEach(item => {             
            let mediaDisplay = item.image_path ? 
                `<img src="${item.image_path}">` : 
                `<span class="menu-item-icon-placeholder" style="font-size:1.5rem; display:block; text-align:center;">🍽️</span>`;                          
            
            sectionHtml += `                 
                <div class="card menu-row-item">                     
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
                </div>`;         
        });         
        sectionHtml += `</div></div>`;         
        mainView.innerHTML += sectionHtml;     
    });     
    updateQtyCounters(); 
}

function modifyCartQty(id, name, price, delta) {     
    let existing = cart.find(x => x.id === id);     
    if (existing) {         
        existing.qty += delta;         
        if (existing.qty <= 0) cart = cart.filter(x => x.id !== id);     
    } else if (delta > 0) {         
        cart.push({ id, name, price, qty: 1, notes: "" });     
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
    totalEl.innerText = "₹" + grandTotal; 
}

function triggerOrderSubmitConfirm() {     
    if(cart.length === 0) return alert("Select menu choices first.");     
    
    const interactiveWrap = document.getElementById("promptInteractiveContent");
    interactiveWrap.innerHTML = `
        <div class="prompt-title" id="customPromptTitle">Confirm Order Submission</div>         
        <div class="prompt-desc" id="customPromptMessage">Send this active selection to kitchen tickets?</div>         
        <div class="prompt-actions" id="promptActionButtons">             
            <button class="prompt-btn p-btn-confirm" id="promptConfirmBtn">Yes, Send</button>             
            <button class="prompt-btn p-btn-cancel" onclick="document.getElementById('customPromptModal').style.display='none'">Cancel</button>         
        </div>
    `;

    document.getElementById("customPromptModal").style.display = "flex";     
    
    document.getElementById("promptConfirmBtn").onclick = function() {         
        fetch("api/process_order.php", {             
            method: "POST",             
            headers: {"Content-Type": "application/json"},             
            body: JSON.stringify({ items: cart })         
        }).then(res => res.json()).then(d => {             
            if(d.success) { 
                cart = []; 
                updateQtyCounters(); 
                renderCart(); 
                
                interactiveWrap.innerHTML = `
                    <div class="prompt-title" style="color: var(--success); margin-bottom: 0;">
                        ✔ Order successfully dispatched to kitchen!
                    </div>
                `;
                
                setTimeout(() => {
                    document.getElementById("customPromptModal").style.display = "none";
                }, 500);
            }         
        });     
    }; 
}

function scrollToSection(id, tab) {     
    document.querySelectorAll(".nav-bar .nav-link").forEach(t => t.classList.remove("active"));     
    tab.classList.add("active");     
    if(id === 'all') window.scrollTo({ top: 0, behavior: 'smooth' });     
    else { 
        const target = document.getElementById(id); 
        if(target) target.scrollIntoView({ behavior: 'smooth' }); 
    } 
}
window.onload = loadCatalog; 
</script> 
<?php include "includes/footer.php"; ?>