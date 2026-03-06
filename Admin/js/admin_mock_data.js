window.AdminMockData = {
  stats: { ventes: 145, revenus: 4250000, commandes: 312, clients: 97 },
  salesSeries: [12, 19, 14, 23, 17, 28, 31, 26, 32, 29, 35, 40],
  products: [
    { name: 'CrÃ¨me concentrÃ©e 450g', category: 'CrÃ¨me', price: 1300, stock: 45, status: 'active' },
    { name: 'CrÃ¨me non concentrÃ©e 450g', category: 'CrÃ¨me', price: 750, stock: 12, status: 'active' },
    { name: 'Huile 1L', category: 'Huile', price: 1250, stock: 7, status: 'low' },
    { name: 'Boisson 25cl', category: 'Boisson', price: 200, stock: 0, status: 'inactive' }
  ],
  inventory: [
    { product: 'CrÃ¨me concentrÃ©e 450g', qty: 45, warehouse: 'Cotonou', status: 'in-stock', updated: '2026-02-15 11:30' },
    { product: 'Huile 1L', qty: 7, warehouse: 'Calavi', status: 'low', updated: '2026-02-16 09:10' },
    { product: 'Boisson 25cl', qty: 0, warehouse: 'Calavi', status: 'out', updated: '2026-02-16 10:25' }
  ],
  orders: [
    { number: 'ORD-20260217-A1B2C3', client: 'John Doe', date: '2026-02-17', amount: 18500, status: 'paid', payment: 'Simulator' },
    { number: 'ORD-20260216-D4E5F6', client: 'Awa K.', date: '2026-02-16', amount: 7600, status: 'pending', payment: 'Simulator' },
    { number: 'ORD-20260215-G7H8I9', client: 'Paul A.', date: '2026-02-15', amount: 3200, status: 'processing', payment: 'Simulator' }
  ],
  clients: [
    { name: 'John Doe', email: 'john@demo.com', phone: '0144000001', orders: 12, spent: 215000, status: 'active' },
    { name: 'Awa K.', email: 'awa@demo.com', phone: '0144000002', orders: 5, spent: 56000, status: 'active' },
    { name: 'Paul A.', email: 'paul@demo.com', phone: '0144000003', orders: 1, spent: 3200, status: 'pending' }
  ],
  users: [
    { name: 'Admin', email: 'admin@freshy.local', role: 'Super Admin', status: 'active' },
    { name: 'Marie Ops', email: 'ops@freshy.local', role: 'Gestionnaire', status: 'active' },
    { name: 'Support 1', email: 'support@freshy.local', role: 'Support', status: 'inactive' }
  ]
};

