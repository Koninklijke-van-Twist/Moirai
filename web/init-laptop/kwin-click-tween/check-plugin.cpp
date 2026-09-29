// SPDX-License-Identifier: GPL-2.0-or-later
// Validate the factory without constructing an effect or touching the desktop.
#include <QCoreApplication>
#include <QJsonObject>
#include <QPluginLoader>
#include <QDebug>

int main(int argc, char **argv)
{
    QCoreApplication app(argc, argv);
    if (argc != 2) {
        return 2;
    }
    QPluginLoader loader(QString::fromLocal8Bit(argv[1]));
    const auto metadata = loader.metaData().value("MetaData").toObject().value("KPlugin").toObject();
    if (metadata.value("Id").toString() != "clicktween" || !loader.instance()) {
        qCritical() << "Plugin check failed:" << loader.errorString();
        return 1;
    }
    qInfo() << "Plugin metadata and factory loading: OK";
    return 0;
}
