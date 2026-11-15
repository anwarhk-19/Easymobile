import { createContext, useCallback, useContext, useState } from 'react';

const ToastContext = createContext(() => {});

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const push = useCallback((message, tone = 'success') => {
        const id = Math.random().toString(36).slice(2);
        setToasts((t) => [...t, { id, message, tone }]);
        setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), tone === 'error' ? 6000 : 3500);
    }, []);
    const tones = { success: 'bg-slate-900 text-white', error: 'bg-red-600 text-white', info: 'bg-brand-700 text-white' };

    return (
        <ToastContext.Provider value={push}>
            {children}
            <div className="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex flex-col items-center gap-2 px-4 md:bottom-6" aria-live="polite">
                {toasts.map((t) => (
                    <div key={t.id} role="status" className={`pointer-events-auto max-w-md rounded-xl px-4 py-3 text-sm font-medium shadow-lg ${tones[t.tone]}`}>{t.message}</div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export const useToast = () => useContext(ToastContext);
