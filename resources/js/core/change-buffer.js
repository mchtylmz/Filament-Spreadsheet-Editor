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

        if (value === oldValue) {
            this.changes.delete(key);
        } else {
            this.changes.set(key, { rowId, field, value, oldValue });
        }

        this.notify();
    }

    clear() {
        this.changes.clear();
        this.notify();
    }

    hasChanges() {
        return this.changes.size > 0;
    }

    all() {
        return Array.from(this.changes.values());
    }

    onChange(listener) {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    }

    notify() {
        const payload = {
            hasChanges: this.hasChanges(),
            changes: this.all(),
        };

        this.listeners.forEach((listener) => listener(payload));
    }
}
