(function adminProductsView() {
  const tbody = document.getElementById('productsTableBody');
  if (!tbody) return;
  const data = (window.AdminMockData && window.AdminMockData.products) || [];
  tbody.innerHTML = data.map((p) => `
    <tr>
      <td>${p.name}</td>
      <td>${p.category}</td>
      <td>${Number(p.price).toLocaleString('fr-FR')} Fcfa</td>
      <td>${p.stock}</td>
      <td><span class="admin-status admin-status--${p.status}">${p.status}</span></td>
      <td><button class="admin-btn">Voir</button> <button class="admin-btn">Modifier</button></td>
    </tr>
  `).join('');
})();

