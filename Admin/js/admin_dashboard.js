(function adminDashboard() {
  const data = window.AdminMockData || {};

  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };

  setText('statVentes', String(data?.stats?.ventes ?? 0));
  setText('statRevenus', `${Number(data?.stats?.revenus ?? 0).toLocaleString('fr-FR')} Fcfa`);
  setText('statCommandes', String(data?.stats?.commandes ?? 0));
  setText('statClients', String(data?.stats?.clients ?? 0));

  const recentTable = document.getElementById('dashboardRecentOrders');
  if (recentTable && Array.isArray(data.orders)) {
    recentTable.innerHTML = data.orders.map((order) => `
      <tr>
        <td>${order.number}</td>
        <td>${order.client}</td>
        <td>${Number(order.amount).toLocaleString('fr-FR')} Fcfa</td>
        <td><span class="admin-status admin-status--${order.status}">${order.status}</span></td>
      </tr>
    `).join('');
  }

  const canvas = document.getElementById('salesChart');
  if (!canvas || typeof window.Chart === 'undefined') return;

  new window.Chart(canvas, {
    type: 'line',
    data: {
      labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
      datasets: [{
        label: 'Ventes',
        data: data.salesSeries || [],
        borderColor: '#4f46e5',
        backgroundColor: 'rgba(79,70,229,0.12)',
        borderWidth: 2,
        fill: true,
        tension: 0.35,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } },
    },
  });
})();



