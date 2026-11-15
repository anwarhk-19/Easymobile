import { Link } from 'react-router-dom';
import Icon from './icons';
import { Badge, StatusBadge, Stars } from './ui';
import { money, time12 } from '../lib/format';

const CAT_ICON = { smartphones: 'smartphone', chargers: 'plug', earphones: 'earbuds', headphones: 'headphones', 'mobile-covers': 'case', 'screen-protectors': 'shield', 'power-banks': 'battery', smartwatches: 'watch', cables: 'cable' };
const HUES = ['from-brand-50 to-brand-100 text-brand-600', 'from-sky-50 to-sky-100 text-sky-600', 'from-amber-50 to-amber-100 text-amber-600', 'from-violet-50 to-violet-100 text-violet-600', 'from-rose-50 to-rose-100 text-rose-500'];

export function ProductImage({ product, className = 'aspect-square' }) {
    const url = product.primary_image?.url || product.images?.[0]?.url;
    if (url) return <img src={url} alt={product.name} loading="lazy" className={`${className} w-full bg-white object-contain`} />;
    const hue = HUES[(product.category_id || product.id || 0) % HUES.length];
    return (
        <div className={`${className} flex w-full items-center justify-center bg-gradient-to-br ${hue}`} aria-hidden="true">
            <Icon name={CAT_ICON[product.category?.slug] || 'box'} className="h-1/3 w-1/3" strokeWidth={1.3} />
        </div>
    );
}

export function Freshness({ freshness }) {
    if (!freshness) return null;
    const tone = { fresh: 'text-emerald-700', recent: 'text-slate-500', stale: 'text-amber-700', unknown: 'text-slate-400' }[freshness.level];
    return <span className={`inline-flex items-center gap-1 text-xs ${tone}`}><Icon name="clock" className="h-3.5 w-3.5" />{freshness.label}</span>;
}

export function ProductCard({ product }) {
    const discounted = product.discount_price && Number(product.discount_price) < Number(product.price);
    return (
        <Link to={`/products/${product.slug}`} className="card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md">
            <div className="relative">
                <ProductImage product={product} />
                {product.availability !== 'in_stock' && <span className="absolute left-2 top-2"><StatusBadge status={product.availability} /></span>}
                {discounted && <span className="absolute right-2 top-2 rounded-full bg-accent-500 px-2 py-0.5 text-[11px] font-bold text-slate-900">{Math.round((1 - product.discount_price / product.price) * 100)}% off</span>}
            </div>
            <div className="flex flex-1 flex-col gap-1 p-3">
                <p className="line-clamp-2 text-sm font-semibold leading-snug text-slate-900 group-hover:text-brand-700">{product.name}</p>
                <p className="text-xs text-slate-500">{product.shop?.name}{product.shop?.area ? ` · ${product.shop.area.name}` : ''}</p>
                <div className="mt-auto flex items-baseline gap-2 pt-1">
                    <span className="text-base font-extrabold text-slate-900">{money(product.effective_price)}</span>
                    {discounted && <span className="text-xs text-slate-400 line-through">{money(product.price)}</span>}
                </div>
                <Freshness freshness={product.stock_freshness} />
            </div>
        </Link>
    );
}

export function ShopCard({ shop }) {
    return (
        <Link to={`/shops/${shop.slug}`} className="card flex gap-3 p-4 transition hover:border-brand-500/50 hover:shadow-md">
            <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-brand-50 text-lg font-extrabold text-brand-700">
                {shop.logo_url ? <img src={shop.logo_url} alt="" className="h-full w-full object-cover" /> : shop.name.split(' ').map((w) => w[0]).slice(0, 2).join('')}
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <p className="truncate font-bold text-slate-900">{shop.name}</p>
                    {shop.is_open_now !== null && <Badge tone={shop.is_open_now ? 'green' : 'slate'}>{shop.is_open_now ? 'Open' : 'Closed'}</Badge>}
                </div>
                <p className="mt-0.5 flex items-center gap-1 text-xs text-slate-500"><Icon name="pin" className="h-3.5 w-3.5" />{shop.area?.name}</p>
                <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600">
                    <Stars value={shop.rating_avg} count={shop.rating_count} size="h-3.5 w-3.5" />
                    {shop.delivery_available && <span className="inline-flex items-center gap-1"><Icon name="truck" className="h-3.5 w-3.5" />Delivery</span>}
                    {shop.products_count !== undefined && <span>{shop.products_count} products</span>}
                </div>
            </div>
        </Link>
    );
}

export function TechnicianCard({ tech }) {
    return (
        <Link to={`/technicians/${tech.slug}`} className="card flex flex-col gap-3 p-4 transition hover:border-brand-500/50 hover:shadow-md">
            <div className="flex items-center gap-3">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-600">
                    {tech.photo_url ? <img src={tech.photo_url} alt="" className="h-full w-full rounded-full object-cover" /> : <Icon name="wrench" />}
                </div>
                <div className="min-w-0">
                    <p className="truncate font-bold text-slate-900">{tech.name}</p>
                    <p className="text-xs text-slate-500">{tech.experience_years}+ yrs · {tech.area?.name}</p>
                </div>
            </div>
            <Stars value={tech.rating_avg} count={tech.rating_count} size="h-3.5 w-3.5" />
            <div className="flex flex-wrap gap-1.5">
                {(tech.repair_categories || []).slice(0, 3).map((c) => <Badge key={c.id}>{c.name}</Badge>)}
                {(tech.repair_categories || []).length > 3 && <Badge>+{tech.repair_categories.length - 3}</Badge>}
            </div>
            <div className="mt-auto flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
                {tech.pickup_available && <span className="inline-flex items-center gap-1"><Icon name="truck" className="h-3.5 w-3.5" />Pickup & drop</span>}
                <span className="inline-flex items-center gap-1"><Icon name="shield" className="h-3.5 w-3.5" />{tech.default_warranty_days}-day warranty</span>
                {tech.opens_at && <span className="inline-flex items-center gap-1"><Icon name="clock" className="h-3.5 w-3.5" />{time12(tech.opens_at)}–{time12(tech.closes_at)}</span>}
            </div>
        </Link>
    );
}
