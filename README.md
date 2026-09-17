# Centro Automotriz Arley — Sistema de Gestión de Órdenes de Trabajo

MVP de sistema web para taller mecánico (3 mecánicos + 1 admin).

**Stack:** PHP 8 + MySQL + Vanilla JS + CSS  
**Hosting:** Hostinger (compatible con FHJ)  
**Fase 1 (MVP):** Kanban + DVI + Presupuesto + Reparación + QC + Portal Cliente + WhatsApp

---

## Setup Local

### 1. Clonar repo
```bash
git clone <repo-url> taller-arley
cd taller-arley
```

### 2. Configurar .env
```bash
cp .env.example .env
# Editar .env con credenciales locales
```

### 3. Crear BD
```bash
mysql -u root -p < database/01_schema.sql
```

### 4. Correr servidor local
```bash
php -S localhost:8000 -t app/
```

Acceder: `http://localhost:8000`

---

## Estructura

```
├── app/
│   ├── config/config.php        ← Configuración
│   ├── includes/db.php          ← Database class
│   ├── ajax/                    ← Endpoints AJAX
│   ├── assets/                  ← CSS + JS
│   └── uploads/                 ← Fotos (servidas vía script)
├── database/
│   ├── 01_schema.sql            ← Schema inicial
│   └── *_PATCH_*.sql            ← Migraciones futuras
└── README.md
```

---

## Fases

- **Fase 1:** Kanban + auth + recepción + DVI + presupuesto + reparación + QC + portal cliente + notificaciones
- **Fase 2:** Inventario + barcode + gestión de proveedores
- **Fase 3:** Facturación electrónica (Almendro Integrador)

---

## Convenciones

- **Seguridad:** PDO prepared statements SIEMPRE
- **Fotos:** Nunca servir `/uploads/` directo — usar endpoint gateado
- **Validación:** PHP es el gate final (JS es UX)
- **DB:** Tolerancia a migraciones pendientes (columnas opcionales)

---

## Contacts

- **Admin:** Priscilla (Centro Automotriz Arley)
- **Champion:** Manuel (dueño)
- **Dev:** Claude Code
