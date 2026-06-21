export class ChangeBuffer {
    constructor() {
        this.changes = new Map();
        this.listeners = new Set();
    }

    key(rowId, field) {
        return `${rowId}:${field}`;
    }

    set(rowId, field, value, oldValue) {
        const key = this.key(rowId, field);
        const existing = this.changes.get(key);
        const originalValue = existing?.oldValue ?? oldValue;

        if (value === originalValue) {
            this.changes.delete(key);
        } else {
            this.changes.set(key, { rowId, field, value, oldValue: originalValue });
        }

        this.notify({ rowId, field });
    }

    clear() {
        this.changes.clear();
        this.notify();
    }

    remove(rowId, field) {
        this.changes.delete(this.key(rowId, field));
        this.notify({ rowId, field });
    }

    hasChanges() {
        return this.changes.size > 0;
    }

    dirtyRowCount() {
        return new Set(this.all().map((change) => change.rowId)).size;
    }

    has(rowId, field) {
        return this.changes.has(this.key(rowId, field));
    }

    all() {
        return Array.from(this.changes.values());
    }

    onChange(listener) {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    }

    notify(lastChanged = null) {
        const payload = {
            hasChanges: this.hasChanges(),
            changes: this.all(),
            dirtyRowCount: this.dirtyRowCount(),
            lastChanged,
        };

        this.listeners.forEach((listener) => listener(payload));
    }
}
