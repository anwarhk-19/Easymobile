import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Icon from './icons';

export default function SearchBar({ initial = '', large, autoFocus }) {
    const [q, setQ] = useState(initial);
    const navigate = useNavigate();
    return (
        <form role="search" onSubmit={(e) => { e.preventDefault(); if (q.trim()) navigate(`/search?q=${encodeURIComponent(q.trim())}`); }} className="relative">
            <Icon name="search" className={`pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 ${large ? 'h-5 w-5' : 'h-4 w-4'}`} />
            <input type="search" value={q} onChange={(e) => setQ(e.target.value)} autoFocus={autoFocus} aria-label="Search products, shops, repairs"
                placeholder="Search phones, chargers, “Redmi Note 13 cover”, repairs…"
                className={`w-full rounded-2xl border border-slate-200 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100 ${large ? 'py-4 pl-12 pr-28 text-base' : 'py-3 pl-10 pr-24 text-sm'}`} />
            <button type="submit" className={`absolute right-2 top-1/2 -translate-y-1/2 rounded-xl bg-brand-600 font-semibold text-white hover:bg-brand-700 ${large ? 'px-5 py-2.5' : 'px-4 py-2 text-sm'}`}>Search</button>
        </form>
    );
}
