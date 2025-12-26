export class RelatedStore {
  constructor() {
    this.uuidSet = new Set();
  }

  hydrateFromUuids(uuids = []) {
    uuids.forEach((u) => {
      if (u) this.uuidSet.add(String(u).trim());
    });
  }

  hasUuid(uuid) {
    return this.uuidSet.has(String(uuid).trim());
  }

  addUuid(uuid) {
    this.uuidSet.add(String(uuid).trim());
  }

  removeUuid(uuid) {
    this.uuidSet.delete(String(uuid).trim());
  }

  clear() {
    this.uuidSet.clear();
  }
}
