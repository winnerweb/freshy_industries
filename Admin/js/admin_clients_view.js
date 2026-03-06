(function adminClientsView() {
  const tbody = document.getElementById('clientsTableBody');
  if (!tbody) return;
  const data = (window.AdminMockData && window.AdminMockData.clients) || [];
  tbody.innerHTML = data.map((c) => `
    <tr>
      <td>${c.name}</td>
      <td>${c.email}</td>
      <td>${c.phone}</td>
      <td>${c.orders}</td>
      <td>${Number(c.spent).toLocaleString('fr-FR')} Fcfa</td>
      <td><span class="admin-status admin-status--${c.status}">${c.status}</span></td>
      <td><button class="admin-btn">Voir</button></td>
    </tr>
  `).join('');
})();

