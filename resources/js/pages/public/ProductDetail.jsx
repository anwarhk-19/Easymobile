import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Icon from '../../components/icons';
import { Freshness, ProductCard, ProductImage } from '../../components/cards';
import EnquiryModal from '../../components/EnquiryModal';
import OrderRequestModal from '../../components/OrderRequestModal';
import { Async, Badge, Button, Section, StatusBadge, Stars, WhatsAppButton } from '../../components/ui';
import { useApi } from '../../lib/hooks';
import { useAuth } from '../../lib/auth';
import { useToast } from '../../lib/toast';
import { date, money, time12 } from '../../lib/format';

export function ReviewList({ reviews }) {
    if (!reviews?.length) return <p className="text-sm text-slate-500">No reviews yet. Reviews can only be written after a completed purchase or repair.</p>;
    return (
        <ul className="divide-y divide-slate-100">
            {reviews.map((r) => (
                <li key={r.id} className="py-3 first:pt-0">
                    <div className="flex items-center justify-between gap-2"><Stars value={r.rating} /><span className="text-xs text-slate-400">{date(r.created_at)}</span></div>
                    {r.comment && <p className="mt-1 text-sm text-slate-700">{r.comment}</p>}
                    <p className="mt-0.5 text-xs text-slate-500">— {r.customer?.name} · Verified transaction</p>
                </li>
            ))}
        </ul>
    );
}

export function ShopInfo({ shop }) {
    return (
        <div className="space-y-2.5 text-sm text-slate-700">
            <p className="flex gap-2"><Icon name="pin" className="h-4 w-4 shrink-0 text-slate-400" />{shop.address}{shop.area && `, ${shop.area.name}`}</p>
            <p className="flex gap-2"><Icon name="clock" className="h-4 w-4 shrink-0 text-slate-400" />{time12(shop.opens_at)} – {time12(shop.closes_at)}{shop.open_days?.length ? ` · ${shop.open_days.map((d) => d[0].toUpperCase() + d.slice(1)).join(', ')}` : ''}{shop.is_open_now !== null && <Badge tone={shop.is_open_now ? 'green' : 'slate'}>{shop.is_open_now ? 'Open now' : 'Closed now'}</Badge>}</p>
            <p className="flex gap-2"><Icon name="phone" className="h-4 w-4 shrink-0 text-slate-400" /><a href={`tel:+91${shop.phone}`} className="font-semibold text-brand-700">+91 {shop.phone}</a></p>
            <p className="flex gap-2"><Icon name="truck" className="h-4 w-4 shrink-0 text-slate-400" />{[shop.pickup_available && 'Store pickup', shop.delivery_available && `Delivery${Number(shop.delivery_fee) ? ` from ${money(shop.delivery_fee)}` : ' (free)'}`].filter(Boolean).join(' · ')}</p>
            {shop.delivery_notes && <p className="pl-6 text-xs text-slate-500">{shop.delivery_notes}</p>}
            {shop.maps_link && <a href={shop.maps_link} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1.5 pl-6 font-semibold text-brand-700 hover:underline">Open in Google Maps →</a>}
        </div>
    );
}

export default function ProductDetail() {
    const { slug } = useParams();
    const state = useApi(`/products/${slug}`);
    const { user } = useAuth();
    const navigate = useNavigate();
    const toast = useToast();
    const [img, setImg] = useState(0);
    const [modal, setModal] = useState(null);
    const requireCustomer = (m) => (!user ? navigate(`/login?next=/products/${slug}`) : user.role !== 'customer' ? toast('Sign in with a customer account to place orders.', 'error') : setModal(m));

    return (
        <Async state={state}>
            {({ product, shop, whatsapp_link, reviews, related, same_product_other_shops: others }) => {
                const discounted = product.discount_price && Number(product.discount_price) < Number(product.price);
                const unavailable = product.availability === 'out_of_stock';
                return (
                    <div className="pb-24 md:pb-0">
                        <nav className="mb-3 text-sm text-slate-500"><Link to="/search" className="hover:text-brand-700">Products</Link> / <Link to={`/search?category=${product.category.slug}`} className="hover:text-brand-700">{product.category.name}</Link></nav>
                        <div className="grid gap-6 md:grid-cols-2">
                            <div>
                                <div className="card overflow-hidden"><ProductImage product={{ ...product, primary_image: product.images[img] }} className="aspect-square" /></div>
                                {product.images.length > 1 && (
                                    <div className="mt-2 flex gap-2 overflow-x-auto">{product.images.map((im, i) => (
                                        <button key={im.id} onClick={() => setImg(i)} className={`h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 ${i === img ? 'border-brand-600' : 'border-transparent'}`} aria-label={`Image ${i + 1}`}><img src={im.url} alt="" className="h-full w-full object-cover" /></button>
                                    ))}</div>
                                )}
                            </div>
                            <div>
                                {product.brand && <p className="text-sm font-semibold text-brand-700">{product.brand.name}</p>}
                                <h1 className="mt-1 text-2xl font-extrabold leading-tight text-slate-900">{product.name}</h1>
                                <div className="mt-2"><Stars value={product.rating_avg} count={product.rating_count} /></div>
                                <div className="mt-4 flex items-baseline gap-3">
                                    <span className="text-3xl font-extrabold text-slate-900">{money(product.effective_price)}</span>
                                    {discounted && <><span className="text-lg text-slate-400 line-through">{money(product.price)}</span><Badge tone="amber">{Math.round((1 - product.discount_price / product.price) * 100)}% off</Badge></>}
                                </div>
                                <div className="mt-3 flex flex-wrap items-center gap-3"><StatusBadge status={product.availability} />{product.stock_quantity > 0 && product.stock_quantity <= 5 && <span className="text-sm text-amber-700">Only {product.stock_quantity} left</span>}<Freshness freshness={product.stock_freshness} /></div>
                                <p className="mt-1 text-xs text-slate-500">Final price and availability are confirmed by the shop before your order is confirmed.</p>

                                <div className="mt-5 hidden gap-2 md:flex">
                                    <Button size="lg" disabled={unavailable} onClick={() => requireCustomer('order')}><Icon name="bag" />Request order</Button>
                                    <Button size="lg" variant="secondary" onClick={() => requireCustomer('enquiry')}><Icon name="message" />Enquire</Button>
                                    <WhatsAppButton href={whatsapp_link} size="lg">WhatsApp</WhatsAppButton>
                                </div>

                                {product.compatible_models.length > 0 && (
                                    <div className="mt-6"><p className="mb-2 text-sm font-bold text-slate-900">Compatible with</p><div className="flex flex-wrap gap-1.5">{product.compatible_models.map((m) => <Badge key={m.id} tone="teal">{m.brand?.name} {m.name}</Badge>)}</div></div>
                                )}
                                {product.description && <div className="mt-6"><p className="mb-1 text-sm font-bold text-slate-900">Description</p><p className="whitespace-pre-line text-sm leading-relaxed text-slate-700">{product.description}</p></div>}
                                <p className="mt-4 text-xs text-slate-400">SKU {product.sku}</p>
                            </div>
                        </div>

                        <div className="mt-6 grid gap-4 md:grid-cols-2">
                            <Section title={<Link to={`/shops/${shop.slug}`} className="hover:text-brand-700">Sold by {shop.name} →</Link>} action={<Stars value={shop.rating_avg} count={shop.rating_count} size="h-3.5 w-3.5" />}><ShopInfo shop={shop} /></Section>
                            <Section title="Reviews"><ReviewList reviews={reviews} /></Section>
                        </div>

                        {others.length > 0 && (
                            <Section title="Also available at" className="mt-4">
                                <ul className="divide-y divide-slate-100">{others.map((o) => (
                                    <li key={o.id} className="flex items-center justify-between gap-3 py-2.5 text-sm"><Link to={`/products/${o.slug}`} className="font-semibold text-slate-900 hover:text-brand-700">{o.shop.name}</Link><span className="flex items-center gap-3"><StatusBadge status={o.availability} /><b>{money(o.effective_price)}</b></span></li>
                                ))}</ul>
                            </Section>
                        )}
                        {related.length > 0 && <div className="mt-8"><h2 className="mb-3 text-lg font-extrabold text-slate-900">Similar products</h2><div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">{related.map((p) => <ProductCard key={p.id} product={p} />)}</div></div>}

                        <div className="fixed inset-x-0 bottom-16 z-20 flex gap-2 border-t border-slate-200 bg-white p-3 md:hidden">
                            <WhatsAppButton href={whatsapp_link} className="!px-3"><span className="sr-only">WhatsApp</span></WhatsAppButton>
                            <Button variant="secondary" className="flex-1" onClick={() => requireCustomer('enquiry')}>Enquire</Button>
                            <Button className="flex-1" disabled={unavailable} onClick={() => requireCustomer('order')}>Request order</Button>
                        </div>
                        <EnquiryModal open={modal === 'enquiry'} onClose={() => setModal(null)} product={product} shop={shop} />
                        {modal === 'order' && <OrderRequestModal open onClose={() => setModal(null)} product={product} shop={shop} />}
                    </div>
                );
            }}
        </Async>
    );
}
