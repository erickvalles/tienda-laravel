# Plan: API de tienda (`/api/v1`) — Laravel 13 + Sanctum

> Estado: plan aprobado en alcance, pendiente de implementación.
> Última actualización: 2026-10-06.

## Punto de partida

Esqueleto limpio: solo `users/cache/jobs`, sin `routes/api.php`, sin Sanctum, sin modelos de
dominio. `bootstrap/app.php` ya renderiza JSON para `api/*`.

**Importante**: en Laravel 11+ el grupo `api` **no** trae `throttle:api` por defecto
(`Middleware::$apiLimiter` es `null`). Hay que habilitarlo explícitamente con
`$middleware->throttleApi()`.

Entorno de referencia: Laravel 13.35, PHP 8.4, SQLite, Pest 5.3, Tailwind 4.

## Decisiones tomadas

| Área | Decisión |
|---|---|
| Auth | Sanctum, abilities (`admin`) |
| Producto | Simple con stock (sin variantes) |
| Carrito | Tabla `carts` en servidor |
| Pago | Sin pasarela → pedido `pending` |
| Admin | CRUD protegido por ability `admin` |
| Estilo | `JsonResource` + prefijo `/api/v1` |
| Envío | Tarifas en `config/shop.php` (ver *Preguntas abiertas*) |
| Descuentos | Tabla `coupons` (percent / fixed) |
| Imágenes | Múltiples por producto, una `is_primary` |
| Categorías | Planas (sin jerarquía) |

---

## Fase 1 — Cimientos

1. `php artisan install:api` (Sanctum + `routes/api.php`).
2. `bootstrap/app.php`:
   - `apiPrefix: 'api/v1'`
   - alias `ability` → `Laravel\Sanctum\Http\Middleware\CheckForAnyAbility`,
     `abilities` → `Laravel\Sanctum\Http\Middleware\CheckAbilities`
   - `$middleware->throttleApi()`
   - `RateLimiter::for('login', …)` por `email|ip`, 5/min
3. `config/shop.php` — `currency`, `shipping.methods` (standard/express),
   `shipping.free_over`.
4. `AppServiceProvider::boot()` — `Model::preventLazyLoading()` y
   `Model::preventSilentlyDiscardingAttributes()` fuera de producción.

## Fase 2 — Esquema (9 migraciones vía `make:migration`)

```
categories        name, slug(UQ), description?, is_active
products          category_id(FK restrict), name, slug(UQ), description?,
                  price dec(10,2), stock uint, is_active, softDeletes
                  índices: (category_id, is_active), price, is_active
product_images    product_id(FK cascade), path, is_primary, sort_order
                  índice: (product_id, sort_order)
carts             user_id(FK cascade, UNIQUE → un carrito abierto por usuario)
cart_items        cart_id(FK cascade), product_id(FK restrict), quantity
                  UNIQUE (cart_id, product_id)
orders            user_id(FK restrict), number(UQ), status, subtotal,
                  discount_amount, shipping_cost, total, coupon_code?,
                  shipping_{name,email,phone?,address_line1,address_line2?,
                  city,state?,postal_code,country}
                  índices: (user_id, created_at), status
order_items       order_id(FK cascade), product_id(FK nullOnDelete),
                  product_name ← snapshot, unit_price, quantity, subtotal
coupons           code(UQ), type, value, min_subtotal?, max_discount?,
                  starts_at?, expires_at?, usage_limit?, times_used=0, is_active
users             + is_admin (migración nueva, no editar la existente)
```

`cart_items` guarda **solo cantidad**; el precio se lee siempre de `products.price`. El snapshot de
precio y nombre ocurre en `order_items`.

### Modelo de dinero

```
subtotal        = Σ (unit_price × quantity)
discount_amount = Coupon::discountFor(subtotal)   → 0 si no hay cupón
shipping_cost   = config('shop.shipping…')        → 0 si supera free_over
total           = subtotal − discount_amount + shipping_cost
```

En `orders` no se guarda `discounted_total`: sería redundante con `total`.

## Fase 3 — Dominio

**Enums** (`app/Enums/`)

- `OrderStatus`: `Pending, Paid, Shipped, Delivered, Cancelled` + `canTransitionTo()`,
  `label()`.
- `CouponType`: `Percent, Fixed` + `discountFor(string $subtotal): string`.

Claves en TitleCase y valores en `snake_case` (`pending`, `paid`, `shipped`, `delivered`,
`cancelled`).

**Modelos** — estilo Laravel 13 ya presente en el repo: atributo `#[Fillable]`, atributo
`#[Hidden]`, método `casts()`, atributo `#[Scope]` para scopes locales.

`Category, Product, ProductImage, Cart, CartItem, Order, OrderItem, Coupon` + relaciones en
`User`.

Scopes previstos: `#[Scope] active()` en `Product` y `Category`; `#[Scope] available()` en
`Coupon`.

**Acción de dominio**

`app/Actions/PlaceOrder.php` — toda la lógica de checkout en **una transacción**:

- `lockForUpdate` sobre los productos del carrito
- revalidación de stock y de precio vigente
- resolución y validación del cupón
- cálculo de totales
- creación de `order_items` con snapshot de nombre y precio
- decremento de `products.stock`
- `coupons.times_used++`
- vaciado del carrito

**Recursos** (`app/Http/Resources/`)

`Category, Product, ProductImage, Cart, CartItem, Order, OrderItem, Coupon, User`. Se respeta la
convención de nombres, de modo que `$product->toResource()` funciona sin configuración adicional.

> **Decisión a confirmar**: el dinero se serializa como **string** (`"19.99"`), no float, para no
> perder precisión. Si el frontend necesita numérico, se cambia en el recurso.

## Fase 4 — Endpoints

### Público

```
GET  /api/v1/categories
GET  /api/v1/categories/{category:slug}
GET  /api/v1/products
GET  /api/v1/products/{product:slug}
GET  /api/v1/shipping-methods?subtotal=
POST /api/v1/coupons/validate
```

`GET /products` acepta `category`, `search`, `min_price`, `max_price`, `in_stock`,
`sort` (allow-list: `price_asc|price_desc|newest|name_asc`) y `per_page` (tope 100). Devuelve
`ProductResource::collection()` paginado con eager load de `images`.

### Auth — `auth:sanctum`

```
POST /api/v1/auth/register
POST /api/v1/auth/login      → throttle:login
POST /api/v1/auth/logout     → revoca el token actual
GET  /api/v1/auth/me
```

El token se emite con `['admin']` si el usuario es admin, y con `[]` en caso contrario.

### Carrito — `auth:sanctum`

```
GET    /api/v1/cart
POST   /api/v1/cart/items
PATCH  /api/v1/cart/items/{cartItem}
DELETE /api/v1/cart/items/{cartItem}
DELETE /api/v1/cart
```

Route model binding **con scope** sobre el carrito del usuario: `CartItem` se resuelve siempre
dentro del carrito autenticado, lo que evita IDOR.

### Pedidos — `auth:sanctum`

```
POST /api/v1/orders            → checkout: shipping{…}, shipping_method, coupon_code?
GET  /api/v1/orders
GET  /api/v1/orders/{order}
```

`OrderPolicy` verifica que el pedido pertenezca al usuario o que el usuario sea admin.

### Admin — `auth:sanctum` + `ability:admin`

```
apiResource('admin/categories')
apiResource('admin/products')
apiResource('admin/coupons')
POST   /admin/products/{product}/images
DELETE /admin/product-images/{image}
POST   /admin/product-images/{image}/primary
GET    /api/v1/admin/orders
GET    /api/v1/admin/orders/{order}
PATCH  /api/v1/admin/orders/{order}/status
```

Validación de imágenes: `File::image()->max('2mb')`, disco `public` con nombre generado
(`products/`), y URL absoluta en el recurso.

## Fase 5 — Seeders, factories y tests

**Factories** para los 8 modelos, con estados útiles:

- `Product::outOfStock()`, `Product::inactive()`
- `Category::inactive()`
- `Coupon::expired()`, `Coupon::percent()`
- `Order::cancelled()`

**Seeders**: `CategorySeeder`, `ProductSeeder` (con imágenes), `CouponSeeder`.
`DatabaseSeeder` crea además un usuario admin y otro normal.

**Pest** — habilitar `RefreshDatabase` en `tests/Pest.php` (está comentado actualmente).

| Archivo | Cubre |
|---|---|
| `CatalogTest` | Filtros, orden, paginación, slug, 404 en inactivo |
| `CartTest` | Alta idempotente, stock insuficiente, aislamiento entre usuarios |
| `CheckoutTest` | Totales con cupón/envío/gratuito, descuento, stock, carrito vaciado |
| `OrderAuthorizationTest` | IDOR: pedido ajeno → 403 |
| `AdminTest` | 403 sin ability, 200 con ability, CRUD |
| `AuthTest` | Registro, login, logout, me |
| `ValidationTest` | Errores 422 en cada Form Request |
| `CouponTypeTest` (unit) | Porcentaje, fijo, tope, mínimo |
| `OrderStatusTest` (unit) | Transiciones válidas e inválidas |

---

## Orden de ejecución y verificación

1. Fases 1–2 → `php artisan migrate --seed`
2. Fases 3–4 → `php artisan route:list --except-vendor` para revisar el mapa completo
3. Fase 5 → `php artisan test --compact`
4. `php artisan storage:link` para servir imágenes
5. `vendor/bin/pint --dirty --format agent`

## Preguntas abiertas

1. **Envío**: tarifas fijas en `config/shop.php`. Si se necesitan métodos de envío
   administrables (CRUD, zonas, pesos), entra la tabla `shipping_methods` y el alcance crece
   bastante.
2. **Cupones**: falta decidir si llevan límite de usos **por usuario**.

## Limitación conocida

Los tests corren en SQLite en memoria, donde `lockForUpdate()` no tiene efecto. El bloqueo de
stock solo es real en MySQL/Postgres. La lógica de checkout está diseñada para ello, pero no es
verificable con la configuración de test actual.