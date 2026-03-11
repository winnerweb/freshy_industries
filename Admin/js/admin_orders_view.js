(function adminOrdersView() {
  const tbody = document.getElementById('ordersTableBody');
  if (!tbody) return;
  const data = (window.AdminMockData && window.AdminMockData.orders) || [];
  tbody.innerHTML = data.map((o) => `
    <tr>
      <td>${o.number}</td>
      <td>${o.client}</td>
      <td>${o.date}</td>
      <td>${Number(o.amount).toLocaleString('fr-FR')} Fcfa</td>
      <td><span class="admin-status admin-status--${o.status}">${o.status}</span></td>
      <td>${o.payment}</td>
      <td><button class="admin-btn">Voir détails</button></td>
    </tr>
  `).join('');
})();



