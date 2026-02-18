# El Horno Rojo - Sistema de Gestión (Paraguay)

Este sistema está configurado para la gestión de "El Horno Rojo", optimizado para Guaraníes (Gs.) y con herramientas específicas para la producción de masa.

## Nuevas Funcionalidades
- **Calculadora de Masa**: Calcula automáticamente agua, aceite, sal y levadura basándose en los kilos de harina, y determina cuántos bollos de 250g se obtienen.
- **Pedidos Mayoristas**: Apartado especial para ventas a grandes clientes con seguimiento de estado (Pendiente, Entregado, Pagado).
- **Promociones y Combos**: Gestión de ofertas especiales y paquetes.
- **Moneda Local**: Todo el sistema utiliza Guaraníes (Gs.) con formato sin decimales.

## Configuración de Pizzas
El sistema viene pre-configurado con las 5 variedades principales:
1. Muzzarella Clasica
2. Muzza y Pesto
3. Pepperoni
4. Cherrys
5. 4 quesos

## Fórmula de Masa (Fija)
- Hidratación: 70% (67% agua, 3% aceite).
- Sal: 20g por kilo de harina.
- Levadura seca: 1g por kilo de harina.

## Instalación y Base de Datos (¡SÚPER FÁCIL!)

**No necesitas crear ninguna base de datos manualmente.** El sistema lo hace por ti la primera vez que lo abres.

1. **Sube los archivos** de la carpeta `php_app` a tu servidor (hosting).
2. **Abre el sistema** en tu navegador (ej: `www.tusitio.com/index.php`).
3. El sistema creará automáticamente un archivo llamado `database.sqlite`. **¡Y listo!** Ya puedes empezar a cargar tus insumos y ventas.

### Notas importantes:
- El sistema empieza **limpio** para que tú crees tus propios productos y recetas.
- Si quieres usar **MySQL** en lugar de SQLite (opcional), lee el archivo `README_MYSQL.md`.
- Asegúrate de que la carpeta donde subas los archivos tenga permisos de escritura (generalmente ya los tiene).
