import { useState } from 'react';
import { Link } from 'react-router-dom';
import Icon from '../../components/icons';
import SearchBar from '../../components/SearchBar';
import { ProductCard, ShopCard, TechnicianCard } from '../../components/cards';
import { Async, CardGridSkeleton } from '../../components/ui';
import { useApi, useMeta } from '../../lib/hooks';

const AREA_KEY = 'bmh_area';
const readArea = () => { try { return localStorage.getItem(AREA_KEY) || ''; } catch { return ''; } };

function Row({ title, to, children }) {
    return (
        <section className="mt-8">
            <div className="mb-3 flex items-end justify-between"><h2 className="text-lg font-extrabold tracking-tight text-slate-900">{title}</h2>{to && <Link to={to} className="text-sm font-semibold text-brand-700 hover:underline">See all</Link>}</div>
            {children}
        </section>
    );
}

export default function Home() {
    const meta = useMeta();
    const [area, setArea] = useState(readArea);
    const home = useApi('/home', { area_id: area });
    const changeArea = (v) => { setArea(v); try { localStorage.setItem(AREA_KEY, v); } catch { /* ignore */ } };

    return (
        <div>
            <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 px-5 py-7 text-white sm:px-8 sm:py-10">
                <div className="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl" aria-hidden="true" />
                <div className="relative max-w-2xl">
                    <div className="mb-4 flex flex-wrap items-center gap-2 text-sm">
                        <Icon name="pin" className="h-4 w-4 text-brand-100" />
                        <label htmlFor="city" className="sr-only">City</label>
                        <select id="city" className="rounded-lg border-0 bg-white/15 px-2 py-1 font-semibold text-white focus:ring-2 focus:ring-white/50" defaultValue="Bhatkal">
                            {(meta?.cities || ['Bhatkal']).map((c) => <option key={c} className="text-slate-900">{c}</option>)}
                        </select>
                        <label htmlFor="area" className="sr-only">Area</label>
                        <select id="area" value={area} onChange={(e) => changeArea(e.target.value)} className="rounded-lg border-0 bg-white/15 px-2 py-1 font-semibold text-white focus:ring-2 focus:ring-white/50">
                            <option value="" className="text-slate-900">All areas</option>
                            {meta?.areas?.map((a) => <option key={a.id} value={a.id} className="text-slate-900">{a.name}</option>)}
                        </select>
                    </div>
                    <h1 className="text-[28px] font-extrabold leading-tight tracking-tight sm:text-4xl">Find it in Bhatkal.<br className="sm:hidden" /> Fix it in Bhatkal.</h1>
                    <p className="mt-2 text-brand-50/90">Live stock from local mobile shops, and repair quotes from verified technicians.</p>
                    <div className="mt-5"><SearchBar large /></div>
                </div>
            </section>

            <Row title="Shop by category">
                <div className="no-scrollbar -mx-4 flex gap-3 overflow-x-auto px-4 pb-1 sm:grid sm:grid-cols-5 sm:overflow-visible lg:grid-cols-10">
                    {meta?.categories?.map((c) => (
                        <Link key={c.id} to={`/search?category=${c.slug}`} className="card flex w-24 shrink-0 flex-col items-center gap-2 p-3 text-center transition hover:border-brand-500 sm:w-auto">
                            <span className="rounded-xl bg-brand-50 p-2.5 text-brand-600"><Icon name={c.icon} className="h-6 w-6" /></span>
                            <span className="text-xs font-semibold leading-tight text-slate-700">{c.name}</span>
                        </Link>
                    ))}
                </div>
            </Row>

            <Async state={home} skeleton={<div className="mt-8"><CardGridSkeleton /></div>}>
                {(d) => (
                    <>
                        {d.banners?.length > 0 && (
                            <div className="mt-8 grid gap-3 md:grid-cols-2">
                                {d.banners.map((b, i) => (
                                    <Link key={i} to={b.link} className={`group flex items-center justify-between gap-4 rounded-2xl p-5 ${b.tone === 'accent' ? 'bg-accent-50 ring-1 ring-accent-100' : 'bg-white ring-1 ring-slate-200'}`}>
                                        <div><p className="font-extrabold text-slate-900">{b.title}</p><p className="mt-1 text-sm text-slate-600">{b.subtitle}</p><p className="mt-3 text-sm font-bold text-brand-700 group-hover:underline">{b.cta} →</p></div>
                                        <Icon name={b.tone === 'accent' ? 'wrench' : 'store'} className="hidden h-14 w-14 shrink-0 text-brand-600/70 sm:block" strokeWidth={1.2} />
                                    </Link>
                                ))}
                            </div>
                        )}
                        {d.popular_accessories.length > 0 && <Row title="Popular accessories" to="/search?availability=available"><div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">{d.popular_accessories.map((p) => <ProductCard key={p.id} product={p} />)}</div></Row>}
                        {d.featured_shops.length > 0 && <Row title="Featured shops" to="/shops"><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{d.featured_shops.map((s) => <ShopCard key={s.id} shop={s} />)}</div></Row>}
                        {d.recent_products.length > 0 && <Row title="Recently added" to="/search?sort=newest"><div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">{d.recent_products.map((p) => <ProductCard key={p.id} product={p} />)}</div></Row>}
                        <Row title="Mobile repair services" to="/repairs">
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                {d.repair_categories.map((c) => (
                                    <Link key={c.id} to={`/repairs?category=${c.slug}`} className="card flex items-center gap-2.5 p-3 transition hover:border-accent-500">
                                        <span className="rounded-lg bg-accent-50 p-2 text-accent-600"><Icon name={c.icon} className="h-5 w-5" /></span>
                                        <span className="text-xs font-semibold leading-tight text-slate-700">{c.name}<span className="block font-normal text-slate-500">{c.technicians_count} technicians</span></span>
                                    </Link>
                                ))}
                            </div>
                        </Row>
                        {d.top_technicians.length > 0 && <Row title="Top-rated technicians" to="/repairs"><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{d.top_technicians.map((t) => <TechnicianCard key={t.id} tech={t} />)}</div></Row>}
                    </>
                )}
            </Async>
        </div>
    );
}
