import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from './api';

/** Loads data from the API, re-running when `deps` change. Returns { data, error, loading, reload, setData }. */
export function useApi(path, params = null, deps = []) {
    const [state, setState] = useState({ data: null, error: null, loading: !!path });
    const ctrl = useRef();
    const key = path ? path + JSON.stringify(params || {}) : null;

    const load = useCallback(() => {
        if (!path) return;
        ctrl.current?.abort();
        ctrl.current = new AbortController();
        setState((s) => ({ ...s, loading: true, error: null }));
        api(path, { params, signal: ctrl.current.signal })
            .then((data) => setState({ data, error: null, loading: false }))
            .catch((error) => error.name !== 'AbortError' && setState({ data: null, error, loading: false }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key, ...deps]);

    useEffect(() => {
        load();
        return () => ctrl.current?.abort();
    }, [load]);

    return { ...state, reload: load, setData: (d) => setState((s) => ({ ...s, data: typeof d === 'function' ? d(s.data) : d })) };
}

/** Wraps an async action with busy state + error capture for forms and buttons. */
export function useAction(fn, { onSuccess, onError } = {}) {
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState({});
    const run = async (...args) => {
        setBusy(true);
        setErrors({});
        try {
            const r = await fn(...args);
            onSuccess?.(r);
            return r;
        } catch (e) {
            setErrors(e.errors || {});
            onError?.(e);
            return undefined;
        } finally {
            setBusy(false);
        }
    };
    return { run, busy, errors, setErrors };
}

let metaCache = null;
let metaPromise = null;
export function useMeta() {
    const [meta, setMeta] = useState(metaCache);
    useEffect(() => {
        if (metaCache) return;
        metaPromise ??= api('/meta').then((m) => (metaCache = m));
        metaPromise.then(setMeta).catch(() => { metaPromise = null; });
    }, []);
    return meta;
}

export function useDebounced(value, ms = 300) {
    const [v, setV] = useState(value);
    useEffect(() => {
        const t = setTimeout(() => setV(value), ms);
        return () => clearTimeout(t);
    }, [value, ms]);
    return v;
}

export function useForm(initial) {
    const [values, setValues] = useState(initial);
    const set = (k) => (e) => {
        const v = e?.target ? (e.target.type === 'checkbox' ? e.target.checked : e.target.type === 'file' ? (e.target.multiple ? [...e.target.files] : e.target.files[0]) : e.target.value) : e;
        setValues((s) => ({ ...s, [k]: v }));
    };
    return { values, setValues, set, field: (k) => ({ value: values[k] ?? '', onChange: set(k), name: k }) };
}
